# Set up for Development on DrupalPod

## First, what is Gitpod?
Gitpod can provide fully initialized, perfectly set-up developer environments
for any kind of software project. It is a container-based developer platform
that puts developer experience first. Gitpod provides ready-to-code developer
environments in the cloud accessible through your browser or your local IDE.

## What is DrupalPod?
DrupalPod allows you to work on Drupal contributions with the familiar setup of 
a “normal” Drupal website. No local LAMP stack is needed because it is all 
running in the cloud.

## How to configure DrupalPod?
1. Download the DrupalPod browser extension:
[Chrome](https://chrome.google.com/webstore/detail/drupalpod-helper-extensio/pjfjhkcfkhbemnbpkakjhmboacefmjjl?hl=en)
or [Firefox](https://addons.mozilla.org/en-US/firefox/addon/drupalpod)
2. Go to any issue page on Drupal.org (core, module, or theme)
3. Click on the DrupalPod extension
4. (Optional) Choose a patch / issue fork / branch

_NOTE: We recommend choosing the branch/issue fork, the "standard" profile, and
the latest version of Drupal (9.4.x at the time of this writing)._

## DrupalPod - Pushing Code
In order to push code a one time SSH key setup is required.

## From within a Gitpod workspace run:
1.  .gitpod/drupal/ssh/02-setup-private-ssh-sh
2.  Follow the instructions on the screen.
3.  .gitpod/drupal/ssh/04-confirm-ssh-setup.sh
4.  If SSH keys are valid, it stores your private SSH key as an environment
variable in Gitpod.

# Contributing without DrupalPod

-   Follow the [Git instructions](https://www.drupal.org/project/project_browser/git-instructions)
to clone project browser to your site.
-   In the `/project_browser` directory, install PHP dependencies with `composer
install`.
-   In the `/project_browser/sveltejs` directory install JS dependencies `yarn
install` and run the dev script `yarn dev` which will watch for filesystem
changes.
    -   Note: `yarn dev` will report the app is available at localhost,
    but it is better to use the fully available version in your Drupal site 
    at admin/modules/browse

If you are working on the Svelte frontend, you will need to install Svelte 
dependencies and start a "watcher" process. This will watch for changes that
affect the frontend and automatically re-compile.

```
cd sveltejs
yarn install
yarn dev
```

*__NOTE__: Every commit that affects any of the Svelte files should include the
compiled bundle.css, bundle.js, and bundle.js.map files. Even if you have been
watching with a `yarn dev` and compiling as you go, you must use `yarn build`
from the `sveltejs` folder in order to run a one-time production build to 
have your changes accepted.*

Another thing to consider is that we are working in the contrib space so we can
iterate quickly, but with an eye toward getting Project Browser accepted into
Drupal core. To that end, we should not do anything that we know would not be
allowed in core, and we are working against Claro as the theme it should
initially work with. Be sure to test using Claro as the admin theme.

# Why Svelte?

This module uses Svelte as a "frontend" framework. There are many reasons to
choose Svelte, but the primary reason is that it does not require this module
(or Drupal Core for that matter) to "ship" a frontend framework. Svelte is only
used during the development process. Before "shipping," the Svelte code is
compiled into "vanilla" HTML, CSS, and JS.

This avoids many of the security and deprecation issues that have historically
arisen from shipping jQuery with Drupal.

It also avoids many of the licensing, performance, and dependency concerns posed
by the possibility of using frameworks like React or Vue.

# Rebase a branch

If new changes are pushed to the origin branch, you might need to rebase a fork
branch with the latest changes by rebasing it.

If a merge request needs rebase:
- First check if a blue "rebase" button is available in the GitLab UI. If so, use that. That means it can rebase clean with no conflicts.
- If there are going to be conflicts, you need to **rebase it locally**. 

## Rebase locally

To get that set up:

1. Go to the issue fork repo by clicking the issue fork from the issue.
2. Open the "Clone" blue dropbutton, and copy the git link (Clone with SSH)
3. Clone it somewhere - `git clone <paste>` 
4. Go into that folder `cd <folder it cloned to>`
5. Add the original project as an upstream remote: `git remote add upstream git@git.drupal.org:project/project_browser.git`
6. Ensure you're on the 1.0.x branch of the local issue fork (`git checkout 1.0.x` if you're not)
7. Then pull remotes changes into your local 1.0.x `git pull --rebase upstream 1.0.x` - you should see this pull in the latest from the upstream project
8. Check out the issue branch now - `git checkout <your branch>` - the branch should start with the issue number
9. Now that you're on the issue branch, rebase it on top of the latest 1.0.x: `git rebase 1.0.x`
10. You will have conflicts. to resolve:
   - Anything in `sveltejs/public/build` can be resolve by just rebuilding over the top of the files that are there: `yarn install && yarn build` from the sveltejs folder.
   - Other things will need to be intelligently rebased - contact the person whose work you may be dealing with from 1.0.x if needed, or tim.plunkett or chrisfromredfin in #project-browser
11. Files that are marked "both modified" in a git status need to be dealt with. if you have generated a new change or file, it will be in "changes not staged for commit" - generally you will want to check those out and ignore those changes. `git checkout -f <yarn.lock, ex.g.>`
12. Ones that are marked "unmerged" need to be added, then continue with your rebase: `git add <file>` and then `git rebase --continue`
13. Now that you're rebased, you will have divergent branches; to resolve, you force push to the issue fork branch: `git push --force origin`
14. On the merge request (when you push it gives you the link right to it), you should now see a blue 'merge' button


# What to work on?

We’re currently in the process of working toward an MVP, a true 1.0.0 release in
contrib. We have a “Contrib Kanban” board of issues that are required for the
MVP to be a success, so those issues may be the best ones to start with:

[https://contribkanban.com/board/ProjectBrowserMVP](https://contribkanban.com/board/ProjectBrowserMVP)

Otherwise, you can find any issue in the issue queue and push it through!
