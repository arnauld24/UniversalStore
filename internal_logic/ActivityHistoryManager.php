<?php

namespace internal_logic;

use external_server\Connect;
use PDO;
use DateTime;

class ActivityHistoryManager {
    
    private $conn;
    
    public function __construct() {
        $this->conn = new Connect();
    }
    
    /**
     * Get all activities for a user with different types
     */
    public function getUserActivityHistory($user_id, $limit = 50) {
        $activities = [];
        
        // Fetch payment transactions
        $activities = array_merge($activities, $this->getPaymentActivities($user_id, $limit));
        
        // Fetch order activities
        $activities = array_merge($activities, $this->getOrderActivities($user_id, $limit));
        
        // Fetch account activities
        $activities = array_merge($activities, $this->getAccountActivities($user_id, $limit));
        
        // Fetch login activities
        $activities = array_merge($activities, $this->getLoginActivities($user_id, $limit));
        
        // Fetch wishlist activities
        $activities = array_merge($activities, $this->getWishlistActivities($user_id, $limit));
        
        // Sort by date (newest first)
        usort($activities, function($a, $b) {
            $dateA = new DateTime($a['timestamp']);
            $dateB = new DateTime($b['timestamp']);
            return $dateB->getTimestamp() - $dateA->getTimestamp();
        });
        
        // Return only the top $limit
        return array_slice($activities, 0, $limit);
    }
    
    /**
     * Get paginated activities with filtering and search
     */
    public function getPaginatedActivities($user_id, $filters = [], $search = '', $page = 1, $per_page = 20) {
        $activities = [];
        
        // Get all activities first
        $all_activities = $this->getUserActivityHistory($user_id, 1000); // Get more for filtering
        
        // Apply filters
        if (!empty($filters['type'])) {
            $all_activities = array_filter($all_activities, function($activity) use ($filters) {
                return $activity['activity_type'] === $filters['type'];
            });
        }
        
        // Apply search
        if (!empty($search)) {
            $all_activities = array_filter($all_activities, function($activity) use ($search) {
                $searchable = strtolower($search);
                $description = strtolower($activity['description'] ?? '');
                $order_id = strtolower($activity['order_id'] ?? '');
                $payment_id = strtolower($activity['payment_id'] ?? '');
                
                return strpos($description, $searchable) !== false ||
                       strpos($order_id, $searchable) !== false ||
                       strpos($payment_id, $searchable) !== false;
            });
        }
        
        // Apply date range filter
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $all_activities = array_filter($all_activities, function($activity) use ($filters) {
                $activity_date = new DateTime($activity['timestamp']);
                
                if (!empty($filters['date_from'])) {
                    $from_date = new DateTime($filters['date_from']);
                    if ($activity_date < $from_date) return false;
                }
                
                if (!empty($filters['date_to'])) {
                    $to_date = new DateTime($filters['date_to']);
                    if ($activity_date > $to_date) return false;
                }
                
                return true;
            });
        }
        
        // Calculate pagination
        $total_activities = count($all_activities);
        $total_pages = ceil($total_activities / $per_page);
        $offset = ($page - 1) * $per_page;
        
        // Get paginated results
        $paginated_activities = array_slice($all_activities, $offset, $per_page);
        
        return [
            'activities' => $paginated_activities,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_activities' => $total_activities,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Get activity statistics
     */
    public function getActivityStats($user_id) {
        $all_activities = $this->getUserActivityHistory($user_id, 1000);
        
        $stats = [
            'total' => count($all_activities),
            'by_type' => [],
            'by_month' => [],
            'recent' => 0
        ];
        
        $one_month_ago = new DateTime('-30 days');
        
        foreach ($all_activities as $activity) {
            $type = $activity['activity_type'];
            $date = new DateTime($activity['timestamp']);
            
            // Count by type
            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = 0;
            }
            $stats['by_type'][$type]++;
            
            // Count by month
            $month_key = $date->format('Y-m');
            if (!isset($stats['by_month'][$month_key])) {
                $stats['by_month'][$month_key] = 0;
            }
            $stats['by_month'][$month_key]++;
            
            // Count recent activities
            if ($date > $one_month_ago) {
                $stats['recent']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Get payment transaction activities
     */
    private function getPaymentActivities($user_id, $limit) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    p.payment_id,
                    p.order_id,
                    p.amount,
                    p.currency,
                    p.payment_method,
                    p.status,
                    COALESCE(p.completed_at, p.payment_date) as timestamp,
                    o.order_cost,
                    'payment' as activity_type
                FROM payments p
                LEFT JOIN orders o ON p.order_id = o.order_id
                WHERE p.user_id = ?
                ORDER BY p.payment_date DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("Error fetching payment activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get order-related activities
     */
    private function getOrderActivities($user_id, $limit) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    order_id,
                    order_cost as amount,
                    order_status as status,
                    order_date as timestamp,
                    'order' as activity_type
                FROM orders
                WHERE user_id = ?
                ORDER BY order_date DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("Error fetching order activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get account change activities from audit logs
     */
    private function getAccountActivities($user_id, $limit) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    audit_id,
                    'account' as activity_type,
                    action_type as account_action,
                    CONCAT('Account: ', LOWER(REPLACE(action_type, '_', ' '))) as description,
                    created_at as timestamp,
                    status,
                    metadata
                FROM audit_logs
                WHERE user_id = ?
                AND action_type IN ('PASSWORD_CHANGED', 'EMAIL_VERIFIED', 'ADDRESS_UPDATED', 'PROFILE_UPDATED')
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("Error fetching account activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get login activities
     */
    private function getLoginActivities($user_id, $limit) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    user_session,
                    'login' as activity_type,
                    CONCAT('Login - ', browser, ' on ', device) as description,
                    last_login as timestamp,
                    browser,
                    device
                FROM users_session
                WHERE user_id = ?
                AND loggedIn_status = 1
                ORDER BY last_login DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("Error fetching login activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get wishlist activities
     */
    private function getWishlistActivities($user_id, $limit) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    audit_id,
                    'wishlist' as activity_type,
                    action_type as wishlist_action,
                    description,
                    created_at as timestamp,
                    status,
                    metadata
                FROM audit_logs
                WHERE user_id = ?
                AND action_type IN ('WISHLIST_ADD', 'WISHLIST_REMOVE')
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            error_log("Error fetching wishlist activities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get formatted activity icon and color based on type and status
     */
    public static function getActivityIcon($activityType, $status = null) {
        $icons = [
            'payment' => [
                'icon' => 'fa-credit-card',
                'color' => 'text-blue-600',
                'bg_color' => 'bg-blue-100'
            ],
            'order' => [
                'icon' => 'fa-box-open',
                'color' => 'text-orange-600',
                'bg_color' => 'bg-orange-100'
            ],
            'account' => [
                'icon' => 'fa-user-gear',
                'color' => 'text-purple-600',
                'bg_color' => 'bg-purple-100'
            ],
            'login' => [
                'icon' => 'fa-sign-in-alt',
                'color' => 'text-green-600',
                'bg_color' => 'bg-green-100'
            ],
            'wishlist' => [
                'icon' => 'fa-heart',
                'color' => 'text-red-600',
                'bg_color' => 'bg-red-100'
            ]
        ];
        
        return $icons[$activityType] ?? $icons['account'];
    }
    
    /**
     * Get status badge styling
     */
    public static function getStatusBadge($status) {
        $badges = [
            'completed' => ['class' => 'bg-green-100 text-green-800', 'label' => 'Completed'],
            'pending' => ['class' => 'bg-yellow-100 text-yellow-800', 'label' => 'Pending'],
            'failed' => ['class' => 'bg-red-100 text-red-800', 'label' => 'Failed'],
            'processing' => ['class' => 'bg-blue-100 text-blue-800', 'label' => 'Processing'],
            'cancelled' => ['class' => 'bg-gray-100 text-gray-800', 'label' => 'Cancelled'],
            'shipped' => ['class' => 'bg-indigo-100 text-indigo-800', 'label' => 'Shipped'],
            'delivered' => ['class' => 'bg-green-100 text-green-800', 'label' => 'Delivered'],
            'pending_payment' => ['class' => 'bg-yellow-100 text-yellow-800', 'label' => 'Pending Payment']
        ];
        
        return $badges[strtolower($status)] ?? ['class' => 'bg-gray-100 text-gray-800', 'label' => ucfirst($status)];
    }
    
    /**
     * Format activity description
     */
    public static function formatActivityDescription($activity) {
        $type = $activity['activity_type'];
        
        switch($type) {
            case 'payment':
                $status = ucfirst($activity['status']);
                return "Payment {$status} - {$activity['payment_method']} - " . 
                       number_format($activity['amount'], 0) . " {$activity['currency']}";
                       
            case 'order':
                return "Order " . ucfirst($activity['status']) . " - Order ID: " . substr($activity['order_id'], 0, 15) . (strlen($activity['order_id']) > 15 ? '...' : '');
                
            case 'account':
                $action = isset($activity['account_action']) ? $activity['account_action'] : $type;
                $action_label = str_replace('_', ' ', $action);
                return ucfirst($action_label);
                
            case 'login':
                return $activity['description'] ?? "Login Activity";
                
            case 'wishlist':
                $action = isset($activity['wishlist_action']) ? $activity['wishlist_action'] : $type;
                if ($action === 'WISHLIST_ADD') {
                    return "Added item to wishlist";
                } elseif ($action === 'WISHLIST_REMOVE') {
                    return "Removed item from wishlist";
                }
                return "Wishlist Activity";
                
            default:
                return "Activity";
        }
    }
    
    /**
     * Get formatted timestamp
     */
    public static function formatTimestamp($timestamp) {
        $date = new DateTime($timestamp);
        $now = new DateTime();
        $interval = $now->diff($date);
        
        if ($interval->days == 0) {
            if ($interval->h == 0) {
                return $interval->i . " minutes ago";
            }
            return $interval->h . " hours ago";
        } elseif ($interval->days == 1) {
            return "Yesterday";
        } elseif ($interval->days < 7) {
            return $interval->days . " days ago";
        } else {
            return $date->format('M d, Y');
        }
    }
}
