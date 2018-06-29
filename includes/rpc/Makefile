all: docs

docs:
	ordbogen-phpdoc --exclude=/tests/ --source=$(PWD) --include-path=.:$(PWD)/../ --target=mysql://root:Vj4bE2Ls5@lan.db.dev.ordbogen.com/phpdoc?RPC%20API

docs-prod:
	ordbogen-phpdoc --exclude=/tests/ --source=$(PWD) --include-path=.:$(PWD)/../ --target=mysql://root:Vj4bE2Ls5@lan.master2.ordbogen.com/phpdoc?RPC%20API

.PHONY: docs docs-prod all
