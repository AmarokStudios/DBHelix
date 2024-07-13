
# DBHelix

DBHelix is a PHP library designed to simplify database interactions by providing a structured and consistent approach to managing database connections and queries.

## Features

- **Easy Database Connections**: Quickly establish and manage database connections with minimal configuration.
- **Query Builder**: Build complex SQL queries programmatically.
- **Error Handling**: Robust error handling and reporting for database operations.
- **Secure**: Ensures secure handling of database credentials and queries.
- **Lightweight**: Minimal dependencies and optimized for performance.

## Installation

To install DBHelix, clone the repository and include it in your project:

```bash
git clone https://github.com/AmarokStudios/DBHelix.git
```

## Usage

### Establishing a Connection

To establish a connection to your database, use the `Database` class. Ensure that you have configured your database credentials in the `.ENV` file.

```php
<?php
require_once 'DBHelix/Database.php';

$db = new Database();
$conn = $db->connect();
?>
```

### Running Queries

You can run SQL queries using the `query` method:

```php
<?php
$result = $db->query("SELECT * FROM users WHERE id = ?", [1]);

if ($result) {
    while ($row = $result->fetch()) {
        echo $row['username'];
    }
} else {
    echo "No user found.";
}
?>
```

### Error Handling

DBHelix provides built-in error handling:

```php
<?php
try {
    $db->query("SELECT * FROM non_existing_table");
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
```

## Configuration

Update the `config.php` file with your database credentials:

```php
<?php
return [
    'host' => 'your_host',
    'dbname' => 'your_dbname',
    'username' => 'your_username',
    'password' => 'your_password'
];
?>
```

## Contributing

We welcome contributions to DBHelix! Please follow these steps to contribute:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Commit your changes (`git commit -am 'Add new feature'`).
4. Push to the branch (`git push origin feature-branch`).
5. Create a new Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Contact

For questions or support, please contact Amarok Studios at [support@amarokstudios.com](mailto:support@amarokstudios.com).

---

*DBHelix is developed and maintained by Amarok Studios.*
