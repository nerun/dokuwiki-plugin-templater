# CHANGELOG

2022-11-24 v0.5
* Fixed some warnings
* Doesn't show the header for templates included in a section when calling `{{template>page#section}}`

2022-11-21 v0.4
* Updated v0.3.1 to work with 2022-07-31 Igor:
  * Updated functions _handle_ (line 91) and _render_ (line 117)
  * Fixed _array_search()_ warning (line 152) by [Jack126Guy](https://github.com/jack126guy)
  * Fixed PHP 7+ error (line 171)
  * Fixed PHP curly braces (line 229)
* This is Daniel Dias Rodrigues' first update

2009-03-21 v0.3.1
* Vincent de Lau's update:
  * Added escaping `|` as `\|`
  * Trimming keys and variables
  * Allowing multiple instances of a template and default values (default default = '')

2005-10-26 v0.2
* Changed the substitution delimiters from `{ }` to `@`
* Perform substitution on parsing rather then on output
* When the template does not exist show a link to create the template

2005-10-25 v0.1
* First Version
