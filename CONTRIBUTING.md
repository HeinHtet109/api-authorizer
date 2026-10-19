# How to contribute

We welcome pull requests with fixes, change suggestions and documentation updates. Every pull request
is reviewed; a change that alters the canonical string, the header names, the error envelope or the
config keys is a breaking change for every client that already signs requests, so it needs a clear
reason and a major release.

## Setup

```bash
composer install
```

There is no application to boot. Tests run through [Orchestra Testbench](https://packages.tools/testbench).

## Running the suite

```bash
composer test          # vendor/bin/pest
vendor/bin/pint        # apply code style
vendor/bin/pint --test # check code style only
```

## Standards

- **Prove the test fails first.** Write the test, watch it fail, then make it pass.
- **Cover behaviour, not implementation.** The files under `src/` are the contract; `tests/` is its
  living documentation.
- **Keep the canonical format stable.** It is the wire format shared with every client SDK. Change it
  only in a major release, and update the README, the tests and the changelog together.
- **Never log or commit secrets.** No real client secret, signature or key belongs in the repository,
  a fixture or a test.
- **README and CHANGELOG stay in sync** with any behaviour, header, config or error-code change.

## Coding conventions

Start reading the code and you will get the hang of it. We optimise for readability:

- indent with four spaces (soft tabs);
- spaces after list items and method parameters (`[1, 2, 3]`, not `[1,2,3]`), around operators
  (`$x += 1`, not `$x+=1`) and around array arrows;
- type everything: parameter, return and property types, with `declare(strict_types=1);` at the top;
- descriptive names over comments. Add a comment only when the reason is not obvious from the code.

Laravel Pint enforces the formatting. Match the style of the code around your change rather than
reformatting unrelated lines — a reformat is welcome as its own pull request and unwelcome mixed into
a behavioural one.

## Using an AI coding agent

If you work with an agent, point it at [`AGENTS.md`](AGENTS.md). It describes the package, the
standards a change must meet, and the traps in this repository.

## Submitting changes

- Add a one-sentence header describing the problem the PR solves.
- Describe the change and why it is needed, and why you chose this approach over another.
- Complete the AI disclosure section of the pull request template.
- Run `composer test` locally before opening the pull request. The suite is not run in CI, so the local run is the only gate.
