# PHP Resque Worker (and Enqueue)

PHP Resque is a Redis-backed library for creating background jobs, placing those
jobs on one or more queues, and processing them later.

![PHP-Resque Logo](https://github.com/resque/php-resque/raw/master/extras/php-resque.png)

[![License (MIT)](https://img.shields.io/packagist/l/resque/php-resque.svg?style=flat-square)](https://github.com/resque/php-resque)
[![PHP Version](https://img.shields.io/packagist/php-v/resque/php-resque.svg?style=flat-square&logo=php&logoColor=white)](https://packagist.org/packages/resque/php-resque)
[![Latest Version](https://img.shields.io/packagist/v/resque/php-resque.svg?style=flat-square)](https://packagist.org/packages/resque/php-resque)
[![Latest Unstable Version](https://img.shields.io/packagist/vpre/resque/php-resque.svg?style=flat-square)](https://packagist.org/packages/resque/php-resque)
[![Downloads](https://img.shields.io/packagist/dt/resque/php-resque.svg?style=flat-square)](https://packagist.org/packages/resque/php-resque)

[![Build Status](https://img.shields.io/travis/resque/php-resque.svg?style=flat-square&logo=travis)](http://travis-ci.org/resque/php-resque)
[![Code Quality](https://img.shields.io/scrutinizer/g/resque/php-resque.svg?style=flat-square&logo=scrutinizer)](https://scrutinizer-ci.com/g/resque/php-resque/)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/resque/php-resque.svg?style=flat-square&logo=scrutinizer)](https://scrutinizer-ci.com/g/resque/php-resque/)
[![Dependency Status](https://img.shields.io/librariesio/github/resque/php-resque.svg?style=flat-square)](https://libraries.io/github/resque/php-resque)

[![Latest Release](https://img.shields.io/github/release/resque/php-resque.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/resque/php-resque)
[![Latest Release Date](https://img.shields.io/github/release-date/resque/php-resque.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/resque/php-resque)
[![Commits Since Latest Release](https://img.shields.io/github/commits-since/resque/php-resque/latest.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/resque/php-resque)
[![Maintenance Status](https://img.shields.io/maintenance/yes/2020.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/resque/php-resque)

[![Contributors](https://img.shields.io/github/contributors/resque/php-resque.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/resque/php-resque)
[![Chat on Slack](https://img.shields.io/badge/chat-Slack-blue.svg?style=flat-square&logo=slack&logoColor=white)](https://join.slack.com/t/php-resque/shared_invite/enQtNTIwODk0OTc1Njg3LWYyODczMTZjMzI2N2JkYWUzM2FlNDk5ZjY2ZGM4Njc4YjFiMzU2ZWFjOGQxMDIyNmE5MTBlNWEzODBiMmVmOTI)

## Background

Resque was pioneered by GitHub, and written in Ruby. What you're seeing here
started life as an almost direct port of the Resque worker and enqueue system to
PHP.

For more information on Resque, visit the official GitHub project:
 <https://github.com/resque/resque>

For further information, see the launch post on the GitHub blog:
 <http://github.com/blog/542-introducing-resque>

> The PHP port does NOT include its own web interface for viewing queue stats,
> as the data is stored in the exact same expected format as the Ruby version of
> Resque.

The PHP port provides much the same features as the Ruby version:

-   Workers can be distributed between multiple machines
-   Includes support for priorities (queues)
-   Resilient to memory leaks (forking)
-   Expects failure

It also supports the following additional features:

-   Has the ability to track the status of jobs
-   Will mark a job as failed, if a forked child running a job does not exit
    with a status code as `0`
-   Has built in support for `setUp` and `tearDown` methods, called pre and post
    jobs

Additionally it includes php-resque-scheduler, a PHP port of [resque-scheduler](http://github.com/resque/resque),
which adds support for scheduling items in the future to Resque. It has been
designed to be an almost direct-copy of the Ruby plugin

At the moment, php-resque-scheduler only supports delayed jobs, which is the
ability to push a job to the queue and have it run at a certain timestamp, or
in a number of seconds. Support for recurring jobs (similar to CRON) is planned
for a future release.

This port was originally made by [Chris
Boulton](https://github.com/chrisboulton), with maintenance by the community.
See <https://github.com/chrisboulton/php-resque> for more on that history.

## Requirements

-   PHP 5.3+
-   Redis 2.2+
-   Optional but Recommended: Composer

## Getting Started

The easiest way to work with php-resque is when it's installed as a Composer
package inside your project. Composer isn't strictly required, but makes life a
lot easier.

If you're not familiar with Composer, please see <http://getcomposer.org/>.

1.  Run `composer require resque/php-resque`.

2.  If you haven't already, add the Composer autoload to your project's
    initialization file. (example)

```php
require 'vendor/autoload.php';
```

## Jobs

### Queueing Jobs

Jobs are queued as follows:

```php
// Required if redis is located elsewhere
Resque::setBackend('localhost:6379');

$args = array(
          'name' => 'Chris'
        );
Resque::enqueue('default', 'My_Job', $args);
```

### Defining Jobs

Each job should be in its own class, and include a `perform` method.

```php
class My_Job
{
    public function perform()
    {
        // Work work work
        echo $this->args['name'];
    }
}
```

When the job is run, the class will be instantiated and any arguments will be
set as an array on the instantiated object, and are accessible via
`$this->args`.

Any exception thrown by a job will result in the job failing - be careful here
and make sure you handle the exceptions that shouldn't result in a job failing.

Jobs can also have `setUp` and `tearDown` methods. If a `setUp` method is
defined, it will be called before the `perform` method is run. The `tearDown`
method, if defined, will be called after the job finishes.

```php
class My_Job
{
    public function setUp()
    {
        // ... Set up environment for this job
    }

    public function perform()
    {
        // .. Run job
    }

    public function tearDown()
    {
        // ... Remove environment for this job
    }
}
```

### Dequeueing Jobs

This method can be used to conveniently remove a job from a queue.

```php
// Removes job class 'My_Job' of queue 'default'
Resque::dequeue('default', ['My_Job']);

// Removes job class 'My_Job' with Job ID '087df5819a790ac666c9608e2234b21e' of queue 'default'
Resque::dequeue('default', ['My_Job' => '087df5819a790ac666c9608e2234b21e']);

// Removes job class 'My_Job' with arguments of queue 'default'
Resque::dequeue('default', ['My_Job' => array('foo' => 1, 'bar' => 2)]);

// Removes multiple jobs
Resque::dequeue('default', ['My_Job', 'My_Job2']);
```

If no jobs are given, this method will dequeue all jobs matching the provided
queue.

```php
// Removes all jobs of queue 'default'
Resque::dequeue('default');
```

### Tracking Job Statuses

php-resque has the ability to perform basic status tracking of a queued job. The
status information will allow you to check if a job is in the queue, is
currently being run, has finished, or has failed.

To track the status of a job, pass `true` as the fourth argument to
`Resque::enqueue`. A token used for tracking the job status will be returned:

```php
$token = Resque::enqueue('default', 'My_Job', $args, true);
echo $token;
```

To fetch the status of a job:

```php
$status = new Resque_Job_Status($token);
echo $status->get(); // Outputs the status
```

Job statuses are defined as constants in the `Resque_Job_Status` class. Valid
statuses include:

-   `Resque_Job_Status::STATUS_WAITING` - Job is still queued
-   `Resque_Job_Status::STATUS_RUNNING` - Job is currently running
-   `Resque_Job_Status::STATUS_FAILED` - Job has failed
-   `Resque_Job_Status::STATUS_COMPLETE` - Job is complete
-   `false` - Failed to fetch the status; is the token valid?

Statuses are available for up to 24 hours after a job has completed or failed,
and are then automatically expired. A status can also forcefully be expired by
calling the `stop()` method on a status class.

### Obtaining job PID ###

You can obtain the PID of the actual process doing the work through `Resque_Job_PID`. On a forking OS this will be the
PID of the forked process.

CAUTION: on a non-forking OS, the PID returned will be of the worker itself.

```php
echo Resque_Job_PID::get($token);
```

Function returns `0` if the `perform` hasn't started yet, or if it has already ended.

## Delayed Jobs

To quote the documentation for the Ruby resque-scheduler:

> Delayed jobs are one-off jobs that you want to be put into a queue at some
point in the future. The classic example is sending an email:

    require 'Resque/Resque.php';
    require 'ResqueScheduler/ResqueScheduler.php';

    $in = 3600;
    $args = array('id' => $user->id);
    ResqueScheduler::enqueueIn($in, 'email', 'SendFollowUpEmail', $args);

The above will store the job for 1 hour in the delayed queue, and then pull the
job off and submit it to the `email` queue in Resque for processing as soon as
a worker is available.

Instead of passing a relative time in seconds, you can also supply a timestamp
as either a DateTime object or integer containing a UNIX timestamp to the
`enqueueAt` method:

    require 'Resque/Resque.php';
    require 'ResqueScheduler/ResqueScheduler.php';

    $time = 1332067214;
    ResqueScheduler::enqueueAt($time, 'email', 'SendFollowUpEmail', $args);

    $datetime = new DateTime('2012-03-18 13:21:49');
    ResqueScheduler::enqueueAt($datetime, 'email', 'SendFollowUpEmail', $args);

NOTE: resque-scheduler does not guarantee a job will fire at the time supplied.
At the time supplied, resque-scheduler will take the job out of the delayed
queue and push it to the appropriate queue in Resque. Your next available Resque
worker will pick the job up. To keep processing as quick as possible, keep your
queues as empty as possible.

## Workers

Workers work in the exact same way as the Ruby workers. For complete
documentation on workers, see the original documentation.

A basic "up-and-running" `bin/resque` file is included that sets up a running
worker environment. (`vendor/bin/resque` when installed via Composer)

The exception to the similarities with the Ruby version of resque is how a
worker is initially setup. To work under all environments, not having a single
environment such as with Ruby, the PHP port makes _no_ assumptions about your
setup.

To start a worker, it's very similar to the Ruby version:

```sh
$ QUEUE=file_serve php bin/resque
```

It's your responsibility to tell the worker which file to include to get your
application underway. You do so by setting the `APP_INCLUDE` environment
variable:

```sh
$ QUEUE=file_serve APP_INCLUDE=../application/init.php php bin/resque
```

_Pro tip: Using Composer? More than likely, you don't need to worry about
`APP_INCLUDE`, because hopefully Composer is responsible for autoloading your
application too!_

Getting your application underway also includes telling the worker your job
classes, by means of either an autoloader or including them.

Alternately, you can always `include('bin/resque')` from your application and
skip setting `APP_INCLUDE` altogether.  Just be sure the various environment
variables are set (`setenv`) before you do.

### Logging

The port supports the same environment variables for logging to STDOUT. Setting
`VERBOSE` will print basic debugging information and `VVERBOSE` will print
detailed information.

```sh
$ VERBOSE=1 QUEUE=file_serve bin/resque
$ VVERBOSE=1 QUEUE=file_serve bin/resque
```

### Priorities and Queue Lists

Similarly, priority and queue list functionality works exactly the same as the
Ruby workers. Multiple queues should be separated with a comma, and the order
that they're supplied in is the order that they're checked in.

As per the original example:

```sh
$ QUEUE=file_serve,warm_cache bin/resque
```

The `file_serve` queue will always be checked for new jobs on each iteration
before the `warm_cache` queue is checked.

### Running All Queues

All queues are supported in the same manner and processed in alphabetical order:

```sh
$ QUEUE='*' bin/resque
```

### Running Multiple Workers

Multiple workers can be launched simultaneously by supplying the `COUNT`
environment variable:

```sh
$ COUNT=5 bin/resque
```

Be aware, however, that each worker is its own fork, and the original process
will shut down as soon as it has spawned `COUNT` forks.  If you need to keep
track of your workers using an external application such as `monit`, you'll need
to work around this limitation.

### Custom prefix

When you have multiple apps using the same Redis database it is better to use a
custom prefix to separate the Resque data:

```sh
$ PREFIX=my-app-name bin/resque
```

### Setting Redis backend ###

When you have the Redis database on a different host than the one the workers
are running, you must set the `REDIS_BACKEND` environment variable:

```sh
$ REDIS_BACKEND=my-redis-ip:my-redis-port bin/resque
```

### Forking

Similarly to the Ruby versions, supported platforms will immediately fork after
picking up a job. The forked child will exit as soon as the job finishes.

The difference with php-resque is that if a forked child does not exit nicely
(PHP error or such), php-resque will automatically fail the job.

### Signals

Signals also work on supported platforms exactly as in the Ruby version of
Resque:

-   `QUIT` - Wait for job to finish processing then exit
-   `TERM` / `INT` - Immediately kill job then exit
-   `USR1` - Immediately kill job but don't exit
-   `USR2` - Pause worker, no new jobs will be processed
-   `CONT` - Resume worker.

### Process Titles/Statuses

The Ruby version of Resque has a nifty feature whereby the process title of the
worker is updated to indicate what the worker is doing, and any forked children
also set their process title with the job being run. This helps identify running
processes on the server and their resque status.

**PHP does not have this functionality by default until 5.5.**

A PECL module (<http://pecl.php.net/package/proctitle>) exists that adds this
functionality to PHP before 5.5, so if you'd like process titles updated,
install the PECL module as well. php-resque will automatically detect and use
it.

### Resque Scheduler

resque-scheduler requires a special worker that runs in the background. This
worker is responsible for pulling items off the schedule/delayed queue and adding
them to the queue for resque. This means that for delayed or scheduled jobs to be
executed, that worker needs to be running.

A basic "up-and-running" `bin/resque-scheduler` file that sets up a
running worker environment is included (`vendor/bin/resque-scheduler` when
installed via composer). It accepts many of the same environment variables as
the main workers for php-resque:

* `REDIS_BACKEND` - Redis server to connect to
* `LOGGING` - Enable logging to STDOUT
* `VERBOSE` - Enable verbose logging
* `VVERBOSE` - Enable very verbose logging
* `INTERVAL` - Sleep for this long before checking scheduled/delayed queues
* `APP_INCLUDE` - Include this file when starting (to launch your app)
* `PIDFILE` - Write the PID of the worker out to this file

It's easy to start the resque-scheduler worker using `bin/resque-scheduler`:
    $ php bin/resque-scheduler

## Event/Hook System

php-resque has a basic event system that can be used by your application to
customize how some of the php-resque internals behave.

You listen in on events (as listed below) by registering with `Resque_Event` and
supplying a callback that you would like triggered when the event is raised:

```sh
Resque_Event::listen('eventName', [callback]);
```

`[callback]` may be anything in PHP that is callable by `call_user_func_array`:

-   A string with the name of a function
-   An array containing an object and method to call
-   An array containing an object and a static method to call
-   A closure (PHP 5.3+)

Events may pass arguments (documented below), so your callback should accept
these arguments.

You can stop listening to an event by calling `Resque_Event::stopListening` with
the same arguments supplied to `Resque_Event::listen`.

It is up to your application to register event listeners. When enqueuing events
in your application, it should be as easy as making sure php-resque is loaded
and calling `Resque_Event::listen`.

When running workers, if you run workers via the default `bin/resque` script,
your `APP_INCLUDE` script should initialize and register any listeners required
for operation. If you have rolled your own worker manager, then it is again your
responsibility to register listeners.

A sample plugin is included in the `extras` directory.

### Events

#### beforeFirstFork

Called once, as a worker initializes. Argument passed is the instance of
`Resque_Worker` that was just initialized.

#### beforeFork

Called before php-resque forks to run a job. Argument passed contains the
instance of `Resque_Job` for the job about to be run.

`beforeFork` is triggered in the **parent** process. Any changes made will be
permanent for as long as the **worker** lives.

#### afterFork

Called after php-resque forks to run a job (but before the job is run). Argument
passed contains the instance of `Resque_Job` for the job about to be run.

`afterFork` is triggered in the **child** process after forking out to complete
a job. Any changes made will only live as long as the **job** is being
processed.

#### beforePerform

Called before the `setUp` and `perform` methods on a job are run. Argument
passed contains the instance of `Resque_Job` for the job about to be run.

You can prevent execution of the job by throwing an exception of
`Resque_Job_DontPerform`. Any other exceptions thrown will be treated as if they
were thrown in a job, causing the job to fail.

#### afterPerform

Called after the `perform` and `tearDown` methods on a job are run. Argument
passed contains the instance of `Resque_Job` that was just run.

Any exceptions thrown will be treated as if they were thrown in a job, causing
the job to be marked as having failed.

#### onFailure

Called whenever a job fails. Arguments passed (in this order) include:

-   Exception - The exception that was thrown when the job failed
-   Resque_Job - The job that failed

#### beforeEnqueue

Called immediately before a job is enqueued using the `Resque::enqueue` method.
Arguments passed (in this order) include:

-   Class - string containing the name of the job to be enqueued
-   Arguments - array of arguments for the job
-   Queue - string containing the name of the queue the job is to be enqueued in
-   ID - string containing the token of the job to be enqueued

You can prevent enqueing of the job by throwing an exception of
`Resque_Job_DontCreate`.

#### afterEnqueue

Called after a job has been queued using the `Resque::enqueue` method. Arguments
passed (in this order) include:

-   Class - string containing the name of scheduled job
-   Arguments - array of arguments supplied to the job
-   Queue - string containing the name of the queue the job was added to
-   ID - string containing the new token of the enqueued job

### afterSchedule

Called after a job has been added to the schedule. Arguments passed are the
timestamp, queue of the job, the class name of the job, and the job's arguments.

### beforeDelayedEnqueue

Called immediately after a job has been pulled off the delayed queue and right
before the job is added to the queue in resque. Arguments passed are the queue
of the job, the class name of the job, and the job's arguments.

## Step-By-Step

For a more in-depth look at what php-resque does under the hood (without needing
to directly examine the code), have a look at `HOWITWORKS.md`.

## Contributors

### Project Creator

-   @chrisboulton

### Project Maintainers

-   @danhunsaker
-   @rajibahmed
-   @steveklabnik

### Others

-   @acinader
-   @ajbonner
-   @andrewjshults
-   @atorres757
-   @benjisg
-   @biinari
-   @cballou
-   @chaitanyakuber
-   @charly22
-   @CyrilMazur
-   @d11wtq
-   @dceballos
-   @ebernhardson
-   @hlegius
-   @hobodave
-   @humancopy
-   @iskandar
-   @JesseObrien
-   @jjfrey
-   @jmathai
-   @joshhawthorne
-   @KevBurnsJr
-   @lboynton
-   @maetl
-   @matteosister
-   @MattHeath
-   @mickhrmweb
-   @Olden
-   @patrickbajao
-   @pedroarnal
-   @ptrofimov
-   @rayward
-   @richardkmiller
-   @Rockstar04
-   @ruudk
-   @salimane
-   @scragg0x
-   @scraton
-   @thedotedge
-   @tonypiper
-   @trimbletodd
-   @warezthebeef
