# common-lb

PHP library providing reusable errors and JSON HTTP responses.
Requires PHP 8.4.1 or newer and Symfony HttpFoundation 8.1.

```php
use NuraBiz\Common\Error\Error;
use NuraBiz\Common\Http\Response\ErrorResponse;
use NuraBiz\Common\Http\Response\SuccessResponse;

$success = new SuccessResponse(data: ['id' => 42], message: 'Created', status: 201);

$error = new Error(code: 'invalid_email', message: 'Invalid email', data: ['field' => 'email']);
$failure = new ErrorResponse($error, status: 422);
```

Responses contain `status`, `data`, and `message`. The `status` field matches
the HTTP status code. For `ErrorResponse`, `data` contains the serialized error
(`code`, `message`, `data`), and the response message comes from that error.

`SuccessResponse` defaults to HTTP 200, `ErrorResponse` to HTTP 400.
Both accept custom response headers. Success responses require a 2xx status
that permits a body (204 and 205 are rejected); errors require a 4xx or 5xx status.

This package has no kernel, routes, controllers, or environment configuration.
Return its responses from the consuming application's HTTP handlers.

Install dependencies with `composer install`.
