# Contributing

## Contribute found bugs.

Please contribute bugs in our [issue queue](https://www.drupal.org/project/issues/ai?categories=All). Real bugs will always be credited to the bug finder, even without a patch.

## Contribute documentation

To contribute documentation, the whole documentation system runs in mkdocs and are stored in the repo under docs for each of the modules and the core.

To try this page locally do the following steps.

1. Install MkDocs: `pip install mkdocs mkdocs-material`
2. Run `mkdocs serve` in the project root
3. Open `http://localhost:8000` in your browser

To contribute documentation, please follow these steps:

1. Create a ticket in our [issue queue](https://www.drupal.org/project/issues/ai?categories=All) with description on what you want to contribute in form of documentation.
2. Click `Get Push Access` to create a repo fork.
3. Download this fork and do you documentation changes.
4. Push them back and do a merge request.

## Contribute testing scripts

Any type of kernel or functional test, based on how the features are supposed to behave would be great to add. The main maintainers do have limited time and a lot of time initially is spent on feature development.

Unit testing generally the developer that created the code should write, but even that we do take merge requests for.

## Contribute manual testing

Any module that is set to experimental are under heavy development, meaning that any feedback we can get on these modules are worth gold. This is true for bugs, UX/UI improvements and missing features.

Please report these via issues in the [issue queue](https://www.drupal.org/project/issues/ai?categories=All).

## Features or bug fixing.

Any type of new features that will contribute to a better AI module will be approved. Not all features will be approved however, so it might make sense to create a feature ticket for discussion before starting to write code.

In general we think that any further AI module that is not a core module that is of vital importance, should be a third party contributed module. But please raise this in tickets as well.

Any bug ticket, anyone is free to fix of course. Any contribution is awesome!

Note that we do require all linters to pass to be able to merge code.
