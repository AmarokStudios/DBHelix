<?php
    namespace DBHelix\Repositories;

    require dirname(__FILE__, 3) . '/vendor/autoload.php';
    use DBHelix\Models\User;

class UserRepository {
    private DatabaseRepository $Database;

    public function __construct(DatabaseRepository $Database) {
        $this->Database = $Database;
    }

    public function find($SearchBy, $Field): ?User {
        $table = 'users';
        $columns = '*';
        $where = "{$SearchBy} = :{$SearchBy}";
        $params = [":{$SearchBy}" => $Field];
        $results = $this->Database->select($table, $columns, $where, $params);

        if (count($results) == 1) {
            $User = new User();
            $User->setID($results[0]["id"]);
            $User->setOrganization($results[0]["organization_id"]); // TODO: Update to add organization instead of ID
            $User->setUsername($results[0]["username"]);
            $User->setPassword($results[0]["password"]);
            $User->setPasswordHash($results[0]["password_hash"]);
            $User->setEmail($results[0]["email"]);
            $User->setIsAdmin($results[0]["is_admin"]);
            $User->setIsActive($results[0]["is_active"]);
            $User->setCreatedAt($results[0]["created_at"]);
            $User->setCreatedBy($results[0]["created_by"]);
            $User->setModifiedAt($results[0]["modified_at"]);
            $User->setModifiedBy($results[0]["modified_by"]);
            return $User;
        }
        return NULL;
    }

    public function findAll($limit = null, $offset = null) {
        $QueryBuilderParams = [
            [
                'Table' => 'users',
                'Columns' => '*',
                'Conditions' => [],
                'OrderBy' => '',
                'Limit' => $limit,
                'Offset' => $offset
            ]
        ];

        $Query = $this->Database->buildSelectQuery(
            $QueryBuilderParams[0]['Table'],
            $QueryBuilderParams[0]['Columns'],
            $QueryBuilderParams[0]['Conditions'],
            $QueryBuilderParams[0]['OrderBy'],
            $QueryBuilderParams[0]['Limit'],
            $QueryBuilderParams[0]['Offset']
        );

        $UserArray = [];
        $Results = $this->Database->query($Query)->fetchAll();

        foreach ($Results as $UserData) {
            $User = new User();
            $User->setID($UserData["id"]);
            $User->setOrganization($UserData["organization_id"]); // TODO: Update to add organization instead of ID
            $User->setUsername($UserData["username"]);
            $User->setEmail($UserData["email"]);
            $User->setPassword($UserData["password"]);
            $User->setPasswordHash($UserData["passwordhash"]);
            $User->setIsAdmin($UserData["is_admin"]);
            $User->setIsActive($UserData["is_active"]);
            $User->setCreatedAt($UserData["created_at"]);
            $User->setCreatedBy($UserData["created_by"]);
            $User->setModifiedAt($UserData["modified_at"]);
            $User->setModifiedBy($UserData["modified_by"]);
            Array_Push($UserArray, $User);
        }

        return $UserArray;
    }

    public function save($user) {
        $table = 'users';
        if ($user->getID() !== null) {
            $data = [
                'organization_id' => $user->getOrganization(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'modified_by' => $user->getModifiedBy()
            ];
            $where = 'id = :id';
            $whereParams = [
                'id' => $user->getID()
            ];
            $this->Database->update($table, $data, $where, $whereParams);
            return true;
        } else {
            $data = [
                'organization_id' => $user->getOrganization(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'password' => $user->getPassword(),
                'password_hash' => $user->getPasswordHash(),
                'created_by' => $user->getCreatedBy(),
                'modified_by' => $user->getModifiedBy()
            ];
            $insertId = $this->Database->insert($table, $data);
            if ($insertId) {
                $user->setID($insertId);
                return $user;
            } else {
                return false;
            }
        }
    }

    public function logFailedLogin($id, $ip, $ua, $cb) {
        $data = [
            'user_id' => $id,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'created_by' => $cb
        ];
        return $this->Database->insert('failed_login_attempts', $data);
    }

    public function delete($user) {
        if ($user->getID() !== null) {
            try {
                $this->Database->delete('users', 'id = :id', ['id' => $user->getID()]);
                return true;
            } catch (\PDOException $e) {
                //echo $e->getMessage();
                return false;
            }
        }
        return false;
    }


}