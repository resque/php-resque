# Contributing to PHP-Resque

First of all: thank you! We appreciate any help you can give PHP-Resque.

Second: before you participate in PHP-Resque, be sure to read our [Code of
Conduct](CODE-OF-CONDUCT.md). Participation indicates you accept and agree to
abide by the guidelines there.

The main way to contribute to PHP-Resque is to write some code! Here's how:

1.  [Fork](https://help.github.com/articles/fork-a-repo) PHP-Resque
2.  Clone your fork - `git clone git@github.com/your-username/php-resque`
3.  Be sure to start from the `develop` branch! - `git checkout develop`
4.  Create a topic branch - `git checkout -b my_branch`
5.  Push to your branch - `git push origin my_branch`
6.  Create a [Pull Request](http://help.github.com/pull-requests/) from your
    branch
7.  That's it!

If you're not just doing some sort of refactoring, a CHANGELOG entry is
appropriate. Please include them in pull requests adding features or fixing
bugs.

Oh, and 80 character columns, please!

## Tests

We use PHPUnit for testing. A simple `vendor/bin/phpunit` will run all the
tests. Make sure they pass when you submit a pull request.

Please include tests with your pull request.

## Documentation

Writing docs is really important. Please include docs in your pull requests.

## Bugs & Feature Requests

You can file bugs on the [issues
tracker](https://github.com/resque/php-resque/issues), and tag them with 'bug'.

When filing a bug, please follow these tips to help us help you:

### Fill In - Don't Replace! - The Template

The sections in the issue template are there to ensure we get all the
information we need to reproduce and fix your bug. Follow the prompts in there,
and everything should be great!

### Reproduction

If possible, please provide some sort of executable reproduction of the issue.
Your application has a lot of things in it, and it might be a complex
interaction between components that causes the issue.

To reproduce the issue, please make a simple Job that demonstrates the essence
of the issue. If the basic job doesn't demonstrate the issue, the issue may be
in another package pulled in by Composer.

### Version information

If you can't provide a reproduction, a copy of your `composer.lock` would be
helpful.
