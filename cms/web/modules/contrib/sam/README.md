# Simple Add More

This module simplifies the multi-value widgets when they have fixed cardinality
(max number of values allowed).

By default, Drupal core will expose on the form the maximum number of values
allowed. This means that if a field can have up to 5 items, for example, the
form will have 5 empty elements. This often leads to bad UX for editors.

This module adds client side JS so that extra empty elements are hidden, and
only the elements below will be displayed:

- On empty fields, only one empty element
- On non-empty fields, only the non-empty elements

This module provides an "Add another item" button to reveal one new empty
element at a time.
