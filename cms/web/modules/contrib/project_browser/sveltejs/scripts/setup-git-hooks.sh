#!/bin/sh

if [ ! $CI ] # don't run in CI environment
then
  echo "Setting git-hooks/ as the project git hooksPath..."
  git config core.hooksPath git-hooks
  echo "Installed git hooks"
fi
