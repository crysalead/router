<?php
namespace Lead\Router\Spec\Suite;

use Lead\Router\ParserException;
use Lead\Router\Parser;

describe("Parser", function() {

    describe("::tokenize()", function() {

        it("parses an empty url", function() {

            $result = Parser::tokenize('');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '',
                'tokens'   => []
            ]);

        });

        it("parses a static url", function() {

            $result = Parser::tokenize('/test');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test',
                'tokens'   => ['/test']
            ]);

        });

        it("parses an url with a variable", function() {

            $result = Parser::tokenize('/test/{param}');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test/{param}',
                'tokens'   => [
                    '/test/',
                    [
                        'name'      => 'param',
                        'pattern'   => '[^/]+'
                    ]
                ]
            ]);

        });

        it("parses an url with several variables", function() {

            $result = Parser::tokenize('/test/{param1}/test2/{param2}');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test/{param1}/test2/{param2}',
                'tokens'   => [
                    '/test/',
                    [
                        'name'      => 'param1',
                        'pattern'   => '[^/]+'
                    ],
                    '/test2/',
                    [
                        'name'      => 'param2',
                        'pattern'   => '[^/]+'
                    ]
                ]
            ]);

        });

        it("parses an url with a variable with a custom regex", function() {

            $result = Parser::tokenize('/test/{param:\d+}');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test/{param:\d+}',
                'tokens'   => [
                    '/test/',
                    [
                        'name'      => 'param',
                        'pattern'   => '\d+'
                    ]
                ]
            ]);

        });

        it("parses an url with an optional segment", function() {

            $result = Parser::tokenize('/test[opt]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test[opt]',
                'tokens'   => [
                    '/test',
                    [
                        'optional' => true,
                        'greedy'   => "?",
                        'repeat'   => false,
                        'pattern'  => 'opt',
                        'tokens'   => [
                            'opt'
                        ]
                    ]
                ]
            ]);

        });

        it("parses an optional segment", function() {

            $result = Parser::tokenize('[test]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '[test]',
                'tokens'   => [
                    [
                        'optional' => true,
                        'greedy'   => "?",
                        'repeat'   => false,
                        'pattern'  => 'test',
                        'tokens'   => [
                            'test'
                        ]
                    ]
                ]
            ]);

        });

        it("parses an optional segment inside a route definition", function() {

            $result = Parser::tokenize('/test[/opt]/required');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test[/opt]/required',
                'tokens'   => [
                    '/test',
                    [
                        'optional' => true,
                        'greedy'   => "?",
                        'repeat'   => false,
                        'pattern'  => '/opt',
                        'tokens'   => [
                            '/opt'
                        ]
                    ],
                    '/required'
                ]
            ]);

        });

        it("parses an url with an optional variable", function() {

            $result = Parser::tokenize('/test[/{param}]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test[/{param}]',
                'tokens'   => [
                    '/test',
                    [
                        'optional' => true,
                        'greedy'   => "?",
                        'repeat'   => false,
                        'pattern'  => '/{param}',
                        'tokens'   => [
                            '/',
                            [
                                'name'      => 'param',
                                'pattern'   => '[^/]+'
                            ]
                        ]
                    ]
                ]
            ]);

        });

        it("parses an url with a variable with a prefix and a suffix inside an optional segment", function() {

            $result = Parser::tokenize('/test[/:{param}:]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test[/:{param}:]',
                'tokens'   => [
                    '/test',
                    [
                        'optional' => true,
                        'greedy'   => '?',
                        'repeat'   => false,
                        'pattern'  => '/:{param}:',
                        'tokens'   => [
                            '/:',
                            [
                                'name'      => 'param',
                                'pattern'   => '[^/]+'
                            ],
                            ':'
                        ]
                    ]
                ]
            ]);

        });

        it("parses an url with a variable and an optional segment", function() {

            $result = Parser::tokenize('/{param}[opt]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/{param}[opt]',
                'tokens'   => [
                    '/',
                    [
                        'name'      => 'param',
                        'pattern'   => '[^/]+'
                    ],
                    [
                        'optional' => true,
                        'greedy'   => "?",
                        'repeat'   => false,
                        'pattern'  => 'opt',
                        'tokens'   => [
                            'opt'
                        ]
                    ]
                ]
            ]);

        });

        it("parses nested segments", function() {

            $result = Parser::tokenize('/test[/{name}[/{id:[0-9]+}]]');
            expect($result)->toBe([
                'optional' => false,
                'greedy'   => '',
                'repeat'   => false,
                'pattern'  => '/test[/{name}[/{id:[0-9]+}]]',
                'tokens'   => [
                    '/test',
                    [
                        'optional' => true,
                        'greedy'   => '?',
                        'repeat'   => false,
                        'pattern'  => '/{name}[/{id:[0-9]+}]',
                        'tokens'   => [
                            '/',
                            [
                                'name'    => 'name',
                                'pattern' => '[^/]+'
                            ],
                            [
                                'optional' => true,
                                'greedy'   => '?',
                                'repeat'   => false,
                                'pattern'  => '/{id:[0-9]+}',
                                'tokens'   => [
                                    '/',
                                    [
                                        'name'    => 'id',
                                        'pattern' => '[0-9]+'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]);
        });

        it("throws an exception when there's a missing closing square bracket", function() {

            $closure = function() {
                Parser::tokenize('/test[opt');
            };

            expect($closure)->toThrow(ParserException::squareBracketMismatch());

            $closure = function() {
                Parser::tokenize('/test[opt[opt2]');
            };

            expect($closure)->toThrow(ParserException::squareBracketMismatch());

        });

    });

    describe("::compile()", function() {

        it("compiles a tokens structure", function() {

            $token = Parser::tokenize('/test[/{name}[/{id:[0-9]+}]]');
            $rules = Parser::compile($token);
            expect($rules)->toBe([
                '/test(?:/([^/]+)(?:/([0-9]+))?)?', ['name' => false, 'id' => false]
            ]);

        });

        it("compiles a tokens structure with repeatable patterns", function() {

            $tokens = Parser::tokenize('/test[/{name}[/{id:[0-9]+}]*]');
            $rules = Parser::compile($tokens);
            expect($rules)->toBe([
                '/test(?:/([^/]+)((?:/[0-9]+)*))?', ['name' => false, 'id' => '/{id:[0-9]+}']
            ]);

        });

        it("throws an exception when a placeholder is present several time", function() {

            $closure = function() {
                Parser::compile(Parser::tokenize('/test/{var}/{var}'));
            };

            expect($closure)->toThrow(ParserException::duplicatePlaceholder('var'));

        });

        it("throws an exception when a placeholder is present several time through different segments", function() {

            $closure = function() {
                Parser::compile(Parser::tokenize('/test/{var}[/{var}]'));
            };

            expect($closure)->toThrow(ParserException::duplicatePlaceholder('var'));

        });

        it("throws an exception when multiple placeholder are present in repeatable segments", function() {

            $closure = function() {
                Parser::compile(Parser::tokenize('/test[/{var1}/{var2}]*'));
            };

            expect($closure)->toThrow(ParserException::placeholderExceeded());

        });

    });

});