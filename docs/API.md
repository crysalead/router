## API

### Route patterns

Route pattern are path string with curly brace placeholders. Possible placeholder format are:

* `'{name}'`       - placeholder
* `'{name:regex}'` - placeholder with regex definition.
* `'[{name}]'`     - optional placeholder
* `'[{name}]+'`    - recurring placeholder
* `'[{name}]*'`    - optional recurring placeholder

Variable placeholders may contain only word characters (latin letters, digits, and underscore) and must be unique within the pattern. For placeholders without an explicit regex, a variable placeholder matches any number of characters other than '/' (i.e `[^/]+`).

You can use square brackets (i.e `[]`) to make parts of the pattern optional. For example `/foo[bar]` will match both `/foo` and `/foobar`. Optional parts can be nested and repeatable using the `[]*` or `[]+` syntax. Example: `/{controller}[/{action}[/{args}]*]`.

Examples:
- `'/foo/'`            - Matches only if the path is exactly '/foo/'. There is no special treatment for trailing slashes, and patterns have to match the entire path, not just a prefix.
- `'/user/{id}'`       - Matches '/user/bob' or '/user/1234!!!' or even '/user/bob/details' but not '/user/' or '/user'.
- `'/user/{id:[^/]+}'` - Same as the previous example.
- `'/user[/{id}]'`     - Same as the previous example, but also match '/user'.
- `'/user[/[{id}]]'`   - Same as the previous example, but also match '/user/'.
- `'/user[/{id}]*'`    - Match '/user' as well as 'user/12/34/56'.
- `'/user/{id:[0-9a-fA-F]{1,8}}'` - Only matches if the id parameter consists of 1 to 8 hex digits.
- `'/files/{path:.*}'`            - Matches any URL starting with '/files/' and captures the rest of the path into the parameter 'path'.

Note: the difference between `/{controller}[/{action}[/{args}]*]` and `/{controller}[/{action}[/{args:.*}]]` for example is `args` will be an array using `[/{args}]*` while a unique "slashed" string using `[/{args:.*}]`.
