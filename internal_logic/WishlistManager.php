<?php

namespace internal_logic;

use external_server\Connect;
use PDO;
use Exception;
use PDOException;

class WishlistManager {

    private $conn;

    public function __construct() {
        try {
            $this->conn = new Connect();
            // Ensure connection is established
            if (!$this->conn) {
                throw new Exception("Failed to establish database connection");
            }
        } catch (Exception $e) {
            error_log("WishlistManager initialization error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add product to user's wishlist
     */
    public function addToWishlist($user_id, $product_id) {
        try {
            if (!$user_id || !$product_id) {
                return ['success' => false, 'message' => 'Invalid user or product ID'];
            }

            // Check if product exists
            if (!$this->productExists($product_id)) {
                return ['success' => false, 'message' => 'Product not found'];
            }

            // Check if already in wishlist
            if ($this->isInWishlist($user_id, $product_id)) {
                return ['success' => false, 'message' => 'Product already in wishlist'];
            }

            if (!$this->conn) {
                error_log("Database connection is not initialized in addToWishlist");
                return ['success' => false, 'message' => 'Database connection error'];
            }

            $stmt = $this->conn->prepare("
                INSERT INTO wishlists (user_id, product_id)
                VALUES (?, ?)
            ");

            if (!$stmt) {
                error_log("Failed to prepare statement in addToWishlist: " . implode(", ", $this->conn->errorInfo()));
                return ['success' => false, 'message' => 'Failed to prepare database statement'];
            }

            $result = $stmt->execute([$user_id, $product_id]);

            if ($result) {
                // Log activity for activity history
                $this->logWishlistActivity($user_id, $product_id, 'WISHLIST_ADD');

                return ['success' => true, 'message' => 'Product added to wishlist', 'action' => 'added'];
            }

            error_log("Execute failed in addToWishlist: " . implode(", ", $stmt->errorInfo()));
            return ['success' => false, 'message' => 'Failed to add product to wishlist'];

        } catch (PDOException $e) {
            error_log("PDOException in addToWishlist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Exception in addToWishlist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Remove product from user's wishlist
     */
    public function removeFromWishlist($user_id, $product_id) {
        try {
            if (!$user_id || !$product_id) {
                return ['success' => false, 'message' => 'Invalid user or product ID'];
            }

            if (!$this->conn) {
                error_log("Database connection is not initialized in removeFromWishlist");
                return ['success' => false, 'message' => 'Database connection error'];
            }

            $stmt = $this->conn->prepare("
                DELETE FROM wishlists
                WHERE user_id = ? AND product_id = ?
            ");

            if (!$stmt) {
                error_log("Failed to prepare statement in removeFromWishlist: " . implode(", ", $this->conn->errorInfo()));
                return ['success' => false, 'message' => 'Failed to prepare database statement'];
            }

            $result = $stmt->execute([$user_id, $product_id]);

            if ($result && $stmt->rowCount() > 0) {
                // Log activity for activity history
                $this->logWishlistActivity($user_id, $product_id, 'WISHLIST_REMOVE');

                return ['success' => true, 'message' => 'Product removed from wishlist', 'action' => 'removed'];
            }

            error_log("No rows affected in removeFromWishlist for user: $user_id, product: $product_id");
            return ['success' => false, 'message' => 'Product not found in wishlist'];

        } catch (PDOException $e) {
            error_log("PDOException in removeFromWishlist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Exception in removeFromWishlist: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get user's wishlist with product details
     */
    public function getUserWishlist($user_id, $limit = null, $offset = 0) {
        try {
            $limitClause = $limit ? "LIMIT ? OFFSET ?" : "";

            // First, fetch wishlist items with product details (excluding ratings)
            $stmt = $this->conn->prepare("
                SELECT
                    w.wishlist_id,
                    w.product_id,
                    w.added_at,
                    p.product_name,
                    p.product_price,
                    p.product_image,
                    p.product_category,
                    p.product_main_category,
                    p.product_special_offer,
                    p.product_quantity,
                    p.product_variant
                FROM wishlists w
                INNER JOIN products p ON w.product_id = p.product_id
                WHERE w.user_id = ?
                ORDER BY w.added_at DESC
                {$limitClause}
            ");

            if ($limit) {
                $stmt->execute([$user_id, $limit, $offset]);
            } else {
                $stmt->execute([$user_id]);
            }

            $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($wishlist_items)) {
                return [];
            }

            // Get all product IDs for rating lookup
            $product_ids = array_column($wishlist_items, 'product_id');

            // Fetch ratings for these products
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $reviews_stmt = $this->conn->prepare("
                SELECT
                    product_id,
                    AVG(rating) as product_rating,
                    COUNT(*) as review_count
                FROM product_reviews
                WHERE product_id IN ({$placeholders})
                GROUP BY product_id
            ");
            $reviews_stmt->execute($product_ids);
            $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Create a map of product_id => rating data
            $reviews_map = [];
            foreach ($reviews as $review) {
                $reviews_map[$review['product_id']] = [
                    'product_rating' => floatval($review['product_rating']),
                    'review_count' => intval($review['review_count'])
                ];
            }

            // Merge ratings into wishlist items
            foreach ($wishlist_items as &$item) {
                $product_id = $item['product_id'];
                if (isset($reviews_map[$product_id])) {
                    $item['product_rating'] = $reviews_map[$product_id]['product_rating'];
                    $item['review_count'] = $reviews_map[$product_id]['review_count'];
                    $item['product_review_count'] = $reviews_map[$product_id]['review_count'];
                } else {
                    $item['product_rating'] = 0.0;
                    $item['review_count'] = 0;
                    $item['product_review_count'] = 0;
                }
            }

            return $wishlist_items;

        } catch (PDOException $e) {
            error_log("Error fetching wishlist: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if product is in user's wishlist
     */
    public function isInWishlist($user_id, $product_id) {
        try {
            if (!$this->conn || !$user_id || !$product_id) {
                return false;
            }

            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM wishlists
                WHERE user_id = ? AND product_id = ?
            ");

            if (!$stmt) {
                error_log("Failed to prepare statement in isInWishlist");
                return false;
            }

            $stmt->execute([$user_id, $product_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("PDOException in isInWishlist: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get wishlist count for user
     */
    public function getWishlistCount($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM wishlists
                WHERE user_id = ?
            ");

            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) $result['count'];

        } catch (PDOException $e) {
            error_log("Error getting wishlist count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Toggle product in wishlist (add if not exists, remove if exists)
     */
    public function toggleWishlist($user_id, $product_id) {
        if ($this->isInWishlist($user_id, $product_id)) {
            return $this->removeFromWishlist($user_id, $product_id);
        } else {
            return $this->addToWishlist($user_id, $product_id);
        }
    }

    /**
     * Get wishlist products for multiple users (useful for recommendations)
     */
    public function getMultipleUsersWishlists($user_ids, $limit = 10) {
        try {
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';

            $stmt = $this->conn->prepare("
                SELECT
                    w.user_id,
                    w.product_id,
                    p.product_name,
                    p.product_price,
                    p.product_image,
                    COUNT(*) as user_count
                FROM wishlists w
                INNER JOIN products p ON w.product_id = p.product_id
                WHERE w.user_id IN ({$placeholders})
                GROUP BY w.product_id
                ORDER BY user_count DESC, w.added_at DESC
                LIMIT ?
            ");

            $params = array_merge($user_ids, [$limit]);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("Error fetching multiple wishlists: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if product exists
     */
    private function productExists($product_id) {
        try {
            if (!$this->conn) {
                error_log("Database connection is not initialized in productExists");
                return false;
            }

            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count
                FROM products
                WHERE product_id = ?
            ");

            if (!$stmt) {
                error_log("Failed to prepare statement in productExists");
                return false;
            }

            $stmt->execute([$product_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result && $result['count'] > 0;

        } catch (PDOException $e) {
            error_log("PDOException in productExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log wishlist activity for activity history
     */
    private function logWishlistActivity($user_id, $product_id, $action_type) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs
                (user_id, action_type, description, status, metadata, ip_address, user_agent)
                VALUES (?, ?, ?, 'SUCCESS', ?, ?, ?)
            ");

            $description = $action_type === 'WISHLIST_ADD' ?
                'Added product to wishlist' : 'Removed product from wishlist';

            $metadata = json_encode([
                'product_id' => $product_id,
                'action' => $action_type
            ]);

            $stmt->execute([
                $user_id,
                $action_type,
                $description,
                $metadata,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

        } catch (PDOException $e) {
            error_log("Error logging wishlist activity: " . $e->getMessage());
        }
    }

    /**
     * Get wishlist statistics for user
     */
    public function getWishlistStats($user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    COUNT(*) as total_items,
                    MAX(added_at) as last_added,
                    MIN(added_at) as first_added,
                    AVG(p.product_price) as avg_price
                FROM wishlists w
                INNER JOIN products p ON w.product_id = p.product_id
                WHERE w.user_id = ?
            ");

            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ?: [
                'total_items' => 0,
                'last_added' => null,
                'first_added' => null,
                'avg_price' => 0
            ];

        } catch (PDOException $e) {
            error_log("Error getting wishlist stats: " . $e->getMessage());
            return [
                'total_items' => 0,
                'last_added' => null,
                'first_added' => null,
                'avg_price' => 0
            ];
        }
    }
}
