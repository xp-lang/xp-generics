XP generics for PHP - ChangeLog
===============================

## ?.?.? / ????-??-??

## 2.1.0 / 2024-08-04

* Merged PR #9: Implement resolving `T<string>::class` - @thekid

## 2.0.1 / 2024-08-03

* Fixed non-generic and untyped parameters not being taken into account
  (@thekid)

## 2.0.0 / 2024-03-24

* Dropped support for PHP 7.0 - 7.3 - @thekid
* Made compatible with XP 12, Compiler version 9.0.0 - @thekid

## 1.0.0 / 2024-03-02

* Added PHP 8.4 to the test matrix - @thekid

## 0.7.0 / 2023-10-01

* Merged PR #8: Add support for component type literals - @thekid
* Merged PR #7: Use new reflection API in tests - @thekid

## 0.6.0 / 2023-09-30

* Merged PR #6: Add support for casts - @thekid

## 0.5.1 / 2023-07-27

* Fixed error *Class ... is not a generic definition* when parent class
  was not previously loaded
  (@thekid)

## 0.5.0 / 2023-02-12

* Migrated to new test framework, see xp-framework/rfc#344 - @thekid
* Merged PR #5: Extend generic parent with type arguments - @thekid

## 0.4.0 / 2022-11-07

* Use release version of `xp-framework/compiler` - @thekid

## 0.3.1 / 2022-11-06

* Fixed support for referencing other generic value types - @thekid

## 0.3.0 / 2022-11-06

* Supported extending from generic base classes / parent interfaces
  Requires https://github.com/xp-framework/core/releases/tag/v11.4.6
  (@thekid)
* Added support for declaring and implementing generic interfaces
  (@thekid)

## 0.2.1 / 2022-11-06

* Adjust to changes in `lang.ast.types.IsGeneric` - @thekid

## 0.2.0 / 2022-11-06

* Added support for generic function types such as `function(T, T): int`.
  Requires https://github.com/xp-framework/core/releases/tag/v11.4.5
  (@thekid)

## 0.1.0 / 2022-11-06

* Hello World! First release - @thekid