# Local development environment setup

## Quick setup

A simple setup is enough for most users to start contributing to Automatic Updates. For advanced needs, see [Customizing your setup](#customizing-your-setup) and [Alternative setup options](#alternative-setup-options).

Assuming you meet the [system requirements](#system-requirements), simply run the following command <sup>[[1]](#footnote-1), [[2]](#footnote-2)</sup> from the directory you would like to install your development environment under <sup>[[3]](#footnote-3)</sup>. It will prompt for confirmation before beginning.

```shell
/bin/bash -c "$(curl -fsSL https://git.drupalcode.org/project/automatic_updates/-/raw/3.0.x/scripts/setup_local_dev.sh)"
```

That's it. The success message will display next steps.

## Details and options

---

* [System requirements](#system-requirements)
* [Caveats](#caveats)
* [Customizing your setup](#customizing-your-setup)
* [Alternative setup options](#alternative-setup-options)
* [Maintaining your environment](#maintaining-your-environment)

---

### System requirements
* A *nix-based operating system, such as Linux or macOS.
* Drupal 10 or later.
* Composer 2 or later. (Automatic Updates is not compatible with Composer 1.)
* Git must be installed.

### Customizing your setup
Several details of your setup can be customized via environment variables. Set these before running [the installation command above](#local-development-environment-setup).

```shell
DRUPAL_CORE_BRANCH="10.0.x" # The branch of Drupal core that will be installed.
DRUPAL_CORE_SHALLOW_CLONE="TRUE" # Whether or not to do a "shallow clone" of Drupal core. (Defaults to TRUE.) See note below.
AUTOMATIC_UPDATES_BRANCH="3.0.x" # The branch of the Automatic Updates module that will be installed.
SITE_DIRECTORY="auto_updates_dev" # The path to the directory where the dev environment will be installed.
SITE_HOST=".test" # The path for Drupal's TRUSTED_HOST_PATTERN.
```

Note: A shallow Git clone is much smaller and therefore faster, but it removes the ability to do debugging operations such as `git bisect` or `git blame`. To recover these abilities, [you can convert your repository to a full clone after the fact](https://stackoverflow.com/questions/6802145/how-to-convert-a-git-shallow-clone-to-a-full-clone):

// cSpell:disable
```shell
cd auto-updates-dev
git fetch --unshallow
```
// cSpell:enable

### Alternative setup options
You can download the setup script first to review its contents or modify it before running it:

```shell
curl --output setup_local_dev.sh https://git.drupalcode.org/project/automatic_updates/-/raw/3.0.x/scripts/setup_local_dev.sh

./scripts/setup_local_dev.sh
```

You can clone the whole repository:

```shell
git clone https://git.drupalcode.org/project/automatic_updates.git
cd automatic_updates

./scripts/setup_local_dev.sh
```

To set up your environment manually, use the setup script as a reference.

### Maintaining your environment

* If you want to switch to a different branch of Drupal core during development or if you delete the `vendor` directory, you may will need to do this step again after running `composer install`.
* _DO NOT_ delete the `automatic_updates` module or the `modules` directory it is in or you will lose any code changes forever. As appropriate, commit them and push them to a remote first--unless you don't care about keeping them. (Don't forget branches other than the one you have checked out.)

---

<a name="footnote-1"><sup>1</sup></a> When running a script downloaded from the Web, it is always wise to read its contents first. Ours ([`setup_local_dev.sh`](https://git.drupalcode.org/project/automatic_updates/-/raw/3.0.x/scripts/setup_local_dev.sh)) is documented with code comments to help with that. For a more cautious approach, see [Alternative setup options](#alternative-setup-options) above.

<a name="footnote-2"><sup>2</sup></a> Curl options explained:
[`-f, --fail`](https://curl.se/docs/manpage.html#-f),
[`-s, --silent`](https://curl.se/docs/manpage.html#-s),
[`-S, --show-error`](https://curl.se/docs/manpage.html#-S),
[`-L, --location`](https://curl.se/docs/manpage.html#-L).

<a name="footnote-3"><sup>3</sup></a> For example, run it in `~/Projects` to create your environment at `~/Projects/auto-updates-dev` or `/var/www` to create it at `/var/www/auto-updates-dev`. See [Customizing your setup](#customizing-your-setup) to override this behavior.
