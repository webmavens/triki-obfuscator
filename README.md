# Triki - Laravel Obfuscated Database Dump Package

## Introduction

Triki is a Laravel package that provides an obfuscated database dump using the Triki Crystal obfuscator. This package allows you to specify tables and columns to obfuscate while keeping selected columns intact.

## Requirements

- Laravel
- PHP
- MySQL/pgSQL
- [Crystal Language installed on your system/server](https://crystal-lang.org/install/)

## Important Note (For SQLite users)

If you are using **SQLite**, the dump will **not obfuscate** any data. It will only generate and download a plain SQL file containing the selected tables.

- There is **no need to use or modify** the `obfuscator.cr` file for SQLite.
- Ensure `sqlite3` is installed on your server/system:

```sh
sudo apt install sqlite3


## Installation

To install the package, run the following command:

```sh
composer require webmavens/triki-obfuscator
```

## Configuration

```sh
php artisan vendor:publish --tag=triki-config
```

### Environment Variables

Ensure your `.env` file contains database credentials:

```
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Ensure your `.env` file contains mail configuration:

```
MAIL_MAILER=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
```

### Middleware Authentication Key

To protect Triki routes, add an authentication key in your `.env` file(If you not set, it will be `web-mavens` by default):

```
TRIKI_AUTH_KEY=your_secure_key
```

### Optional Route Protection

Configure Authorized Emails:
Update the `config/triki.php` file to enable authentication and define the allowed emails:

```php
'auth' => [
    'enabled' => true,
    'authorized_emails' => [
        'your.email@example.com',
        'another@example.com',
    ],
],
```

If you want to more secure your route then use authorized_emails in `config/triki.php`

## Usage

### Accessing the Dump Page

You can access the dump interface via the following URL in your browser:

```
http://yourdomain.com/triki/download?auth_key=web-mavens
```

When prompted, enter the authentication key specified in your `.env` file.

### Generating a Dump

1. Select the tables you want to **keep** in the database dump (unchecked tables will be ignored).
2. Provide an email address where the notification will be sent.
3. Click **Start Dump**.


## Obfuscation Logic

The `obfuscator.cr` file in the root directory determines which columns to obfuscate. Example:

```crystal
require "triki"

obfuscator = Triki.new({
  "users" => {
    "email" => :email,
    "password" => :keep,
  },
})

obfuscator.fail_on_unspecified_columns = false
obfuscator.globally_kept_columns = %w[id created_at updated_at]
obfuscator.obfuscate(STDIN, STDOUT)
```

If a table is not specified, it will be included in the dump without obfuscation.
check https://github.com/josacar/triki for more detaisl.

## Queue Job for Dump Generation

The dump generation process runs as a queued job to avoid timeouts. Ensure your queue worker is running:

```sh
php artisan queue:work
```

## Support

For any issues, feel free to create an issue in the [GitHub repository](https://github.com/webmavens/triki).

## License

