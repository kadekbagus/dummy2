#!/usr/bin/env bash
ROOT=$(pwd)
TESTDUMMYDIR="$ROOT/vendor/laracasts/testdummy"
FAKERDIR="$ROOT/vendor/fzaninotto/faker"

if [ -d $TESTDUMMYDIR ]; then
    echo "Directory $TESTDUMMYDIR exists skipping testdummy"
else
    mkdir -p $TESTDUMMYDIR
    curl -sSL https://api.github.com/repos/laracasts/TestDummy/tarball/4f1b1830b3b5d6cc03e52a56d8e8d858e4a5da4b \
      | tar zx -C $TESTDUMMYDIR --strip-components 1
fi

if [ -d $FAKERDIR ]; then
    echo "Directory $FAKERDIR exists skipping install faker"
else
    mkdir -p $FAKERDIR
    curl -sSL https://api.github.com/repos/fzaninotto/Faker/tarball/010c7efedd88bf31141a02719f51fb44c732d5a0 \
      | tar zx -C $FAKERDIR --strip-components 1
fi