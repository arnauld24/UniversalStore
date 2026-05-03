<?php
include '../../server/connection.php';
    class Controller{
        // output user data
        function printData($user_id){
            $connect = new Connect;
            $stmt = $connect->prepare("SELECT * FROM users ORDER BY user_id");
            $stmt -> execute();
            $content = "
            <table class='caontainer table'>
            <thead class='thead-light'>
            <tr>
            <th scope='col'>First name</th>
            <th scope='col'>last name</th>
            <th scope='col'>Avatar </th>
            <th scope='col'>Email</th>
            </tr>
            </thead>
                <tbody>
                ";
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                $content .= "
                <tr>
                <td>".$row['user_fname']."</td>
                <td>".$row['user_lname']."</td>
                <td><img src='".$row['user_avatar']."' alt='avatar' width='50px' height='50px' style='border-radius:50%;'></td>
                <td>".$row['user_email']."</td>
                </tr>
               
                ";
            }
            $content .= ' </tbody></table>';
            return $content;
        }
        // check if user is logged in
        function checkUserStatus($user_id, $user_session){
            $connect = new Connect;
            $user = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :user_session");
            $user->execute([
                ':user_id' => strval($user_id),
                ':user_session' => $user_session
            ]);
            $userinfo = $user->fetch(PDO::FETCH_ASSOC);
            if(!$userinfo){
                return false;
            }else{
                return true;
            }
        }
        // Generate Char
        function generateCode($length){
            try{
            // Generate $length bytes of cryptographically secure random data
            $random_bytes = random_bytes($length);
            // Convert the bytes to a hex string for storage (e.g., 32 bytes -> 64 characters)
            $password = bin2hex($random_bytes);

            return $password;
          }
          catch(Exception $e){
            // Handle the exception if random_bytes() fails
            error_log("Error generating secure random bytes: " . $e->getMessage());
            return null;
          }
        }
        //generating user_id
        function generateUserid($length){
            try{
                $random_bytes = random_bytes($length);
                $user_id = bin2hex($random_bytes);
                return $user_id;
            }
            catch(Exception $e){
                error_log("Error generating user_id: " . $e->getMessage());
                return null;
            }
        }
        //insert data in database
        function insertData($data){
            $connect = new Connect;
            // check if email exist when inserting data
            $stmt = $connect->prepare("SELECT * FROM users WHERE user_email = :email");
            $stmt->execute([':email' => $data['email']]);

            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$info["user_id"]){
                $user_id = $this-> generateUserid(20);
                //hash password to enforce security
               $password = $this-> generateCode(32);
               $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $session = $this-> generateCode(30);
                $insertUser = $connect->prepare("INSERT INTO users (user_id,user_fname,user_lname,user_avatar,user_email,user_password,user_session) VALUES (:user_id,:user_fname,:user_lname,:user_avatar,:user_email,:user_password,:user_session)");
                $insertUser-> execute([
                    ':user_id' => $user_id,
                    ':user_fname' => $data['givenName'],
                    ':user_lname' => $data['familyName'],
                    ':user_avatar' => $data['avatar'],
                    ':user_email' => $data['email'],
                    ':user_password' => $hashedPassword,
                    ':user_session' => $session
                    
                ]);

                if($insertUser){
                    setcookie('user_id', $user_id, time()+60*60*24*30, "/", "");
                    setcookie('user_session', $session, time()+60*60*24*30, "/", "");
                    header("Location: authentication.php");
                     exit();
                }else{
                    return "Error  inserting user data.";
                }

            }else{
                setcookie('user_id', $info['user_id'], time()+60*60*24*30, "/", "");
                setcookie('user_session', $info['user_session'], time()+60*60*24*30, "/", "");
                header("Location: authentication.php");
                exit();
            }
        }
    }



?>