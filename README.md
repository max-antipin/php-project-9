### Hexlet tests and linter status:
[![Actions Status](https://github.com/max-antipin/php-project-9/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/max-antipin/php-project-9/actions)

### SonarQube status:
[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=max-antipin_php-project-9&metric=bugs)](https://sonarcloud.io/summary/new_code?id=max-antipin_php-project-9) 
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=max-antipin_php-project-9&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=max-antipin_php-project-9) 
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=max-antipin_php-project-9&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=max-antipin_php-project-9) 
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=max-antipin_php-project-9&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=max-antipin_php-project-9) 
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=max-antipin_php-project-9&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=max-antipin_php-project-9)

# Учебный проект №3: Анализатор страниц (Hexlet)

Сайт, который анализирует указанные страницы на SEO пригодность.

[View demo](https://php-project-9-77o3.onrender.com/)

## Требования
- PHP >= 8.3
- Make
- Git
- Composer
- Docker

## Установка
```shell
make install
```

## Запуск
```shell
make start
```

С помощью Docker:
```shell
docker build --tag hexlet-php-project-9:latest .
```

Git Bash for Windows:
```shell
docker run -itd --rm -p 8000:8000 -v "/$PWD:/app" --env-file .env --name hexlet-php-project-9 hexlet-php-project-9:latest
```