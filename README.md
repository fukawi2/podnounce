# podnounce
Open-source web-based podcast publishing tool, supporting multiple shows.

## Description

PodNounce (*Podcast Announce*) is a web-based platform to publish and
distribute podcasts, with support for multiple shows. It was initially written
for a local community radio station to allow for publishing of presenters'
shows in podcast format.

## Features

  - Simple, web-based user interface.
    - User interface is responsive (works on desktop, tablet and mobile)
  - Multiple Shows.
  - RSS feeds:
    - Per show; and
    - Site-wide.
  - Deduplication of media files published to multiple shows.
  - Show Notes per episode, with markdown support.
  - Ability to tag shows or episodes as 'explicit'.

## Getting Started

These instructions will get you a copy PodNounce up and running for your own use.

### Prerequisites
PodNounce runs on any basic Linux server with common infrastructure:

  - Linux server (physical or virtual)
  - Web Server (eg, *nginx* or *apache*)
  - PostgreSQL Server
  - PHP 5.4 or later

### Installing

 1. Download a release tarball.
 2. Extract to your document root (eg, `/var/www/html/`)
 3. Create server-writable directorie. For example under apache on CentOS:
   ```
   cd /var/www/html/podnounce
   mkdir tmp uploads
   chown apache tmp uploads
   ```
 4. Create a database and populate with the schema:
   ```
   createuser podnounce
   createdb -O podnounce podnounce
   psql podnounce < postgresql-schema.sql
   ```
 5. Create a configuration file for your database connection:
    `cp podnounce.conf.DIST podnounce.conf` then edit `podnounce.conf`
 6. Open your web browser and point it to your web server. The installation process should automatically start.

## Built With

* [Fat-Free Framework](https://fatfreeframework.com) - The PHP framework
* [Spectre.css](https://picturepan2.github.io/spectre/) - CSS Boilerplate
* [Composer](https://getcomposer.org/) - PHP Dependency Management
* [jQuery](https://jquery.com/) - Client-side JavaScript Framework

## Contributing

Any contributions should follow the [GitHub Flow](https://help.github.com/articles/github-flow/), and you must accept licensing your code under the MIT License in line with the rest of the project.

## Authors

* **Phillip Smith** - *Project Maintainer* - [fukawi2](https://github.com/fukawi2)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details

## Acknowledgments

* [Anthony Ferrara](https://github.com/ircmaxell) for password hashing compatibility library.
* [Emanuil Rusev](https://github.com/erusev/) for MarkDown rendering library.
* zedwood.com for the MP3File class to help process media files.
