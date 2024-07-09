<?php
    namespace DBHelix\Services;
    use DBHelix\Repositories\DatabaseRepository;
    use DBHelix\Repositories\UserRepository;
    use DBHelix\Config;
    use DBHelix\Utils\Logger;
    use DBHelix\Utils\Functions;
    use Exception;

    class UserService {
        private UserRepository $UserRepository;

        public function __construct() {
            $this->UserRepository = new UserRepository(new DatabaseRepository(Config::fromEnv('DEV', dirname(__FILE__, 3) . '/TESTS/TESTS.env'), new Logger("../logs/UserServiceTest.log")));
        }

        public function getUser($SearchBy, $id) {
            return $this->UserRepository->find($SearchBy, $id);
        }

        public function getAllUsers($limit = null, $offset = null) {
            return $this->UserRepository->findAll($limit, $offset);
        }

        public function registerUser($user) {

            if (filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception("Invalid email address.");
            }
            if (strlen($user->getPassword()) < 6) {
                throw new Exception("Password must be at least 6 characters long.");
            }

            // Check if the email is already taken
            if ($this->UserRepository->find('email', $user->getEmail())) {
                throw new Exception("Email is already registered.");
            }

            // Hash the password
            $user->setPassword(password_hash($user->getPassword(), PASSWORD_BCRYPT));

            // Save the user
            Return $this->UserRepository->save($user);

            // Send a welcome email
            //$subject = "Welcome to Our Service";
            //$message = "Hello " . $user['Name'] . ",\n\nThank you for registering with our service!";
            //$this->EmailNotifier->send($user['Email'], $subject, $message);
        }

        public function saveUser($user) {
            return $this->UserRepository->save($user);
        }

        public function authenticate($UoE, $password) {
            $user = $this->UserRepository->find('email', $UoE);
            if (!is_null($user)) {
                if ($user && password_verify($password, $user->getPassword())) {
                    return true;
                } else {
                    $this->logFailedLogin($user->getID(), Functions::GET_CLIENT_IP(), Functions::GET_USER_AGENT(), 'UserService');
                }
            } else {
                $user = $this->UserRepository->find('username', $UoE);
                if (!is_null($user)) {
                    if ($user && password_verify($password, $user->getPassword())) {
                        return true;
                    } else {
                        $this->logFailedLogin($user->getID(), Functions::GET_CLIENT_IP(), Functions::GET_USER_AGENT(), 'UserService');
                    }
                }
            }
            return false;
        }

        private function logFailedLogin($id, $ip, $ua, $cb) {
            $this->UserRepository->logFailedLogin($id, $ip, $ua, $cb);
        }

        public function deleteUser($user) {
            return $this->UserRepository->delete($user);
        }

    //
    //    public function hasRole($user, $role) {
    //        // Basic role-based access control
    //        return in_array($role, explode(',', $user['Roles']));
    //    }
    //

    }