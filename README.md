# Scabbia2 Yaml Component

[This component](https://github.com/eserozvataf/scabbia2-yaml) is a YAML parser allows serialization and deserialization in YAML format.

[![Build Status](https://travis-ci.org/eserozvataf/scabbia2-yaml.png?branch=master)](https://travis-ci.org/eserozvataf/scabbia2-yaml)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/eserozvataf/scabbia2-yaml/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/eserozvataf/scabbia2-yaml/?branch=master)
[![Total Downloads](https://poser.pugx.org/eserozvataf/scabbia2-yaml/downloads.png)](https://packagist.org/packages/eserozvataf/scabbia2-yaml)
[![Latest Stable Version](https://poser.pugx.org/eserozvataf/scabbia2-yaml/v/stable)](https://packagist.org/packages/eserozvataf/scabbia2-yaml)
[![Latest Unstable Version](https://poser.pugx.org/eserozvataf/scabbia2-yaml/v/unstable)](https://packagist.org/packages/eserozvataf/scabbia2-yaml)
[![Documentation Status](https://readthedocs.org/projects/scabbia2-documentation/badge/?version=latest)](https://readthedocs.org/projects/scabbia2-documentation)

## Usage

### Parsing a YAML file

```php
use Scabbia\Yaml\Parser;

$file = file_get_contents('myConfig.yml');

$parser = new Parser();
$config = $parser->parse($file);

var_dump($config);
```

### Writing a YAML file

```php
use Scabbia\Yaml\Dumper;

$config = [
    'type' => 'mongo',
    'username' => 'eserozvataf',
    'password' => 'password'
];

$dumper = new Dumper();
$content = $dumper->dump($config);

file_put_contents('myConfig.yml', $content);
```

## Links
- [List of All Scabbia2 Components](https://github.com/eserozvataf/scabbia2)
- [Documentation](https://readthedocs.org/projects/scabbia2-documentation)
- [Twitter](https://twitter.com/eserozvataf)
- [Contributor List](contributors.md)
- License Information [I](LICENSE-Apache) [II](LICENSE-MIT)


## Contributing
It is publicly open for any contribution. Bugfixes, new features and extra modules are welcome. All contributions should be filed on the [eserozvataf/scabbia2-yaml](https://github.com/eserozvataf/scabbia2-yaml) repository.

* To contribute to code: Fork the repo, push your changes to your fork, and submit a pull request.
* To report a bug: If something does not work, please report it using GitHub issues.
* To support: [![Donate](https://img.shields.io/gratipay/eserozvataf.svg)](https://gratipay.com/eserozvataf/)
