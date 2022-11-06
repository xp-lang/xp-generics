XP generics for PHP
===================

[![Build status on GitHub](https://github.com/xp-lang/xp-generics/workflows/Tests/badge.svg)](https://github.com/xp-lang/xp-generics/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-lang/xp-generics/version.png)](https://packagist.org/packages/xp-lang/xp-generics)

Plugin for the [XP Compiler](https://github.com/xp-framework/compiler/) which adds support for XP generics.

Example
-------

```php
// Declaration
namespace com\example;

class Queue<E> {
  private array<E> $elements;

  public function __construct(E... $elements) {
    $this->elements= $elements;
  }

  public function push(E $element) {
    $this->elements[]= $element;
  }

  public function pop(): ?E {
    return array_pop($this->elements);
  }
}


// Usage
$q= new Queue<string>();
$q->push('Test');

$q->push(123); // lang.IllegalArgumentException
```

Installation
------------
After installing the XP Compiler into your project, also include this plugin.

```bash
$ composer require xp-framework/compiler
# ...

$ composer require xp-lang/xp-generics
# ...
```

No further action is required.

See also
--------
* [XP RFC: Generics](https://github.com/xp-framework/rfc/issues/106) from January 2007
* [XP RFC: Generics optimization](https://github.com/xp-framework/rfc/issues/193) 
* [PHP RFC: Generics](https://wiki.php.net/rfc/generics)
* [HHVM Generics](https://docs.hhvm.com/hack/generics/introduction)