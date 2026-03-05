# NextPointer AADE

Laravel package for validating and retrieving **Greek AFM information** using the **AADE public web service**.

## Features

* Validate AFM numbers
* Check if AFM exists in AADE
* Retrieve business information
* Laravel Facade support
* Laravel auto-discovery

---

# Installation

Install the package via Composer:

```bash
composer require nextpointer/aade
```

Publish the config file:

```bash
php artisan vendor:publish --tag=aade-config
```

---

# Configuration

Add your AADE credentials to `.env`:

```
AADE_USERNAME=your_username
AADE_PASSWORD=your_password
AADE_CALLED_BY=123456789
```

Explanation:

| Variable       | Description                                     |
| -------------- | ----------------------------------------------- |
| AADE_USERNAME  | Username provided by AADE                       |
| AADE_PASSWORD  | Password provided by AADE                       |
| AADE_CALLED_BY | The AFM of the account calling the AADE service |

---

# Usage

Import the facade:

```php
use Aade;
```

## Validate AFM

Check if an AFM is mathematically valid.

```php
Aade::isValid('094259216');
```

Returns:

```
true | false
```

---

## Check if AFM exists

```php
Aade::exists('094259216');
```

Returns:

```
true | false
```

---

## Get AFM information

```php
$data = Aade::info('094259216');
```

Example response:

```php
[
    "success" => true,
    "raw" => "...AADE XML response..."
]
```

---

# Example

```php
use Aade;

if (Aade::isValid($afm)) {

    if (Aade::exists($afm)) {

        $data = Aade::info($afm);

    }

}
```

---

# Requirements

* PHP 8.2+
* Laravel 10 / 11 / 12
* AADE web service credentials

---

# License

MIT License
