<?php

declare(strict_types=1);

namespace Lead\Router\Spec\Suite;

use Lead\Router\Exception\RouterException;
use Lead\Router\Host;

describe("Host", function () {


    describe("->scheme()", function () {


        it("gets/sets the scheme", function () {


            $host = new Host();
            expect($host->getScheme())->toBe('*');
            expect($host->setScheme('https'))->toBe($host);
            expect($host->getScheme())->toBe('https');
        });
    });
    describe("->pattern()", function () {


        it("gets/sets the pattern", function () {


            $host = new Host();
            expect($host->getPattern())->toBe('*');
            expect($host->setPattern('foo.{domain}.bar'))->toBe($host);
            expect($host->getPattern())->toBe('foo.{domain}.bar');
            expect($host->getRegex())->toBe('foo\\.([^.]+)\\.bar');
            expect($host->getVariables())->toBe([
                'domain'    => false
            ]);
        });
        it("updates the regex", function () {


            $host = new Host();
            expect($host->setPattern('foo.{domain}.bar'))->toBe($host);
            expect($host->getRegex())->toBe('foo\\.([^.]+)\\.bar');
            expect($host->setPattern('foo.{domain}.baz'))->toBe($host);
            expect($host->getRegex())->toBe('foo\\.([^.]+)\\.baz');
        });
        it("updates the variables", function () {


            $host = new Host();
            expect($host->setPattern('foo.{domain}.bar'))->toBe($host);
            expect($host->getVariables())->toBe([
                'domain'    => false
            ]);
            expect($host->setPattern('foo.{baz}.bar'))->toBe($host);
            expect($host->getVariables())->toBe([
                'baz'    => false
            ]);
        });
    });
    describe("->match()", function () {


        it("returns `true` when host & scheme matches", function () {


            $host = new Host(['pattern' => 'foo.{domain}.bar', 'scheme' => 'https']);
            expect($host->match(['scheme' => 'https', 'host' => 'foo.baz.bar'], $variables))->toBe(true);
            expect($variables)->toBe(['domain' => 'baz']);
            expect($host->match(['scheme' => 'https', 'host' => 'biz.bar.baz'], $variables))->toBe(false);
            expect($host->match(['scheme' => 'http', 'host' => 'foo.baz.bar'], $variables))->toBe(false);
        });
        it("returns `true` when host matches with a wildcard as host's scheme", function () {


            $host = new Host(['pattern' => 'foo.{domain}.bar', 'scheme' => 'https']);
            expect($host->match(['scheme' => '*', 'host' => 'foo.baz.bar'], $variables))->toBe(true);
            expect($variables)->toBe(['domain' => 'baz']);
            expect($host->match(['scheme' => '*', 'host' => 'biz.baz.bar'], $variables))->toBe(false);
        });
        it("returns `true` when host matches with a wildcard as request's scheme", function () {


            $host = new Host(['pattern' => 'foo.{domain}.bar', 'scheme' => '*']);
            expect($host->match(['scheme' => 'https', 'host' => 'foo.baz.bar'], $variables))->toBe(true);
            expect($variables)->toBe(['domain' => 'baz']);
            expect($host->match(['scheme' => 'https', 'host' => 'biz.baz.bar'], $variables))->toBe(false);
        });
        it("returns `true` when scheme matches with a wildcard as host's pattern", function () {


            $host = new Host(['pattern' => '*', 'scheme' => 'http']);
            expect($host->match(['scheme' => 'http', 'host' => 'foo.baz.bar'], $variables))->toBe(true);
            expect($variables)->toBe([]);
            expect($host->match(['scheme' => 'https', 'host' => 'foo.baz.bar'], $variables))->toBe(false);
        });
        it("returns `true` when scheme matches with a wildcard as request's pattern", function () {


            $host = new Host(['pattern' => 'foo.{domain}.bar', 'scheme' => 'http']);
            expect($host->match(['scheme' => 'http', 'host' => '*'], $variables))->toBe(true);
            expect($variables)->toBe(['domain' => null]);
            expect($host->match(['scheme' => 'https', 'host' => '*'], $variables))->toBe(false);
        });
    });
    describe("->link()", function () {


        it("builds an host link", function () {


            $host = new Host(['pattern' => 'www.{domain}.com', 'scheme' => 'https']);
            $link = $host->link(['domain' => 'example']);
            expect($link)->toBe('https://www.example.com');
        });
        it("builds a scheme less host link", function () {


            $host = new Host(['pattern' => 'www.{domain}.com']);
            $link = $host->link(['domain' => 'example']);
            expect($link)->toBe('//www.example.com');
        });
        it("overrides scheme when passed as parameter", function () {


            $host = new Host(['pattern' => 'www.{domain}.com']);
            $link = $host->link(['domain' => 'example'], [
                'scheme' => 'http'
            ]);
            expect($link)->toBe('http://www.example.com');
        });
        it("processes complex pattern", function () {


            $host = new Host(['pattern' => '[{subdomains}.]*{domain}.com', 'scheme' => 'https']);
            expect($host->link(['domain' => 'example']))->toBe('https://example.com');
            expect($host->link([
                'subdomains' => ['a'],
                'domain' => 'example'
            ]))->toBe('https://a.example.com');
            expect($host->link([
                'subdomains' => ['a', 'b', 'c'],
                'domain' => 'example'
            ]))->toBe('https://a.b.c.example.com');
        });
        it("throws an exception when variables are missing", function () {


            $closure = function () {

                $host = new Host(['pattern' => 'www.{domain}.com']);
                $link = $host->link();
            };
            expect($closure)->toThrow(new RouterException("Missing parameters `'domain'` for host: `'www.{domain}.com'`."));
        });
    });
});
