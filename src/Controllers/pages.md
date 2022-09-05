@page pages-page Creating pages

All page classes need to be in a `Controllers` namespace and inherit from `Lsr\Core\Controller` abstract
class (`Lsr\Interfaces\ControllerInterface`).

## Properties

All page classes have 3 main properties.

These properties define the main information about the page itself and should be set for each page.

#### Title (gets translated)

```php
protected string $title = '';
```

#### Description (gets translated)

```php
protected string $description = '';
```

#### Latte parameters

Set common parameters in the `init()` method.

```php
protected array $params = [];
```

## Methods

All pages have 4 main methods that can be called. These methods are inherited from the `Core\Page` class, but can be
modified.

#### Init

default:

```php
public function init() : void {}
```

#### getTitle

default:

```php
public function getTitle() : string {
   return Constants::SITE_NAME.(!empty($this->title) ? ' - '.lang($this->title) : '');
}
```

#### getDescription

default:

```php
public function getDescription() : string {
  return lang($this->description);
}
   ```

#### view

default:

```php
public function view(string $templateName) : void {
	view($templateName, $this->params);
}
```

#### respond

default:

```php
public function respond(string|array|object $data, int $code = 200, array $headers = []) : never {
	http_response_code($code);

	$dataNormalized = '';
	if (is_string($data)) {
		$dataNormalized = $data;
	}
	else if (is_array($data) || is_object($data)) {
		$dataNormalized = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$headers['Content-Type'] = 'application/json';
	}


	foreach ($headers as $name => $value) {
		header($name.': '.$value);
	}

	echo $dataNormalized;
	exit;
}
```