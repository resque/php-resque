_For an overview of how to **use** php-resque, see `README.md`._

The following is a step-by-step breakdown of how php-resque operates.

## Enqueue Job

What happens when you call `Resque\Resque::enqueue()`?

1.  `Resque\Resque::enqueue()` calls `Resque\JobHandler::create()` with the same arguments it
    received.
2.  `Resque\JobHandler::create()` checks that your `$args` (the third argument) are
    either `null` or in an array
3.  `Resque\JobHandler::create()` generates a job ID (a "token" in most of the docs)
4.  `Resque\JobHandler::create()` pushes the job to the requested queue (first
    argument)
5.  `Resque\JobHandler::create()`, if status monitoring is enabled for the job (fourth
    argument), calls `Resque\Job\Status::create()` with the job ID as its only
    argument
6.  `Resque\Job\Status::create()` creates a key in Redis with the job ID in its
    name, and the current status (as well as a couple of timestamps) as its
    value, then returns control to `Resque\JobHandler::create()`
7.  `Resque\JobHandler::create()` returns control to `Resque\Resque::enqueue()`, with the job
    ID as a return value
8.  `Resque\Resque::enqueue()` triggers the `afterEnqueue` event, then returns control
    to your application, again with the job ID as its return value

## Workers At Work

How do the workers process the queues?

1.  `Resque\Worker\ResqueWorker::work()`, the main loop of the worker process, calls
    `Resque\Worker\ResqueWorker->reserve()` to check for a job
2.  `Resque\Worker\ResqueWorker->reserve()` checks whether to use blocking pops or not (from
    `BLOCKING`), then acts accordingly:

-   Blocking Pop
    1.  `Resque\Worker\ResqueWorker->reserve()` calls `Resque\JobHandler::reserveBlocking()` with
        the entire queue list and the timeout (from `INTERVAL`) as arguments
    2.  `Resque\JobHandler::reserveBlocking()` calls `Resque\Resque::blpop()` (which in turn
        calls Redis' `blpop`, after prepping the queue list for the call, then
        processes the response for consistency with other aspects of the
        library, before finally returning control [and the queue/content of the
        retrieved job, if any] to `Resque\JobHandler::reserveBlocking()`)
    3.  `Resque\JobHandler::reserveBlocking()` checks whether the job content is an
        array (it should contain the job's type [class], payload [args], and
        ID), and aborts processing if not
    4.  `Resque\JobHandler::reserveBlocking()` creates a new `Resque\JobHandler` object with
        the queue and content as constructor arguments to initialize the job
        itself, and returns it, along with control of the process, to
        `Resque\Worker\ResqueWorker->reserve()`
-   Queue Polling
    1.  `Resque\Worker\ResqueWorker->reserve()` iterates through the queue list, calling
        `Resque\JobHandler::reserve()` with the current queue's name as the sole
        argument on each pass
    2.  `Resque\JobHandler::reserve()` passes the queue name on to `Resque\Resque::pop()`,
        which in turn calls Redis' `lpop` with the same argument, then returns
        control (and the job content, if any) to `Resque\JobHandler::reserve()`
    3.  `Resque\JobHandler::reserve()` checks whether the job content is an array (as
        before, it should contain the job's type [class], payload [args], and
        ID), and aborts processing if not
    4.  `Resque\JobHandler::reserve()` creates a new `Resque\JobHandler` object in the same
        manner as above, and also returns this object (along with control of
        the process) to `Resque\Worker\ResqueWorker->reserve()`

3.  In either case, `Resque\Worker\ResqueWorker->reserve()` returns the new `Resque\JobHandler`
    object, along with control, up to `Resque\Worker\ResqueWorker::work()`; if no job is
    found, it simply returns `FALSE`

-   No Jobs
    1.  If blocking mode is not enabled, `Resque\Worker\ResqueWorker::work()` sleeps for
        `INTERVAL` seconds; it calls `usleep()` for this, so fractional seconds
        _are_ supported
-   Job Reserved
    1.  `Resque\Worker\ResqueWorker::work()` triggers a `beforeFork` event
    2.  `Resque\Worker\ResqueWorker::work()` calls `Resque\Worker\ResqueWorker->workingOn()` with the new
        `Resque\JobHandler` object as its argument
    3.  `Resque\Worker\ResqueWorker->workingOn()` does some reference assignments to help
        keep track of the worker/job relationship, then updates the job status
        from `WAITING` to `RUNNING`
    4.  `Resque\Worker\ResqueWorker->workingOn()` stores the new `Resque\JobHandler` object's
        payload in a Redis key associated to the worker itself (this is to
        prevent the job from being lost indefinitely, but does rely on that PID
        never being allocated on that host to a different worker process), then
        returns control to `Resque\Worker\ResqueWorker::work()`
    5.  `Resque\Worker\ResqueWorker::work()` forks a child process to run the actual
        `perform()`
    6.  The next steps differ between the worker and the child, now running in
        separate processes:
    -   Worker
        1.  The worker waits for the job process to complete
        2.  If the exit status is not 0, the worker calls `Resque\JobHandler->fail()`
            with a `Resque\Exceptions\DirtyExitException` as its only argument.
        3.  `Resque\JobHandler->fail()` triggers an `onFailure` event
        4.  `Resque\JobHandler->fail()` updates the job status from `RUNNING` to
            `FAILED`
        5.  `Resque\JobHandler->fail()` calls `Resque\FailureHandler::create()` with the job
            payload, the `Resque\Exceptions\DirtyExitException`, the internal ID of the
            worker, and the queue name as arguments
        6.  `Resque\FailureHandler::create()` creates a new object of whatever type has
            been set as the `Resque\FailureHandler` "backend" handler; by default, this
            is a `Resque\FailureHandler_Redis` object, whose constructor simply
            collects the data passed into `Resque\FailureHandler::create()` and pushes
            it into Redis in the `failed` queue
        7.  `Resque\JobHandler->fail()` increments two failure counters in Redis: one
            for a total count, and one for the worker
        8.  `Resque\JobHandler->fail()` returns control to the worker (still in
            `Resque\Worker\ResqueWorker::work()`) without a value
    -   Job
        1.  `Resque\Job\PID` is created, registering the PID of the actual process
            doing the job.
        2.  The job calls `Resque\Worker\ResqueWorker->perform()` with the `Resque\JobHandler` as
            its only argument.
        3.  `Resque\Worker\ResqueWorker->perform()` sets up a `try...catch` block so it can
            properly handle exceptions by marking jobs as failed (by calling
            `Resque\JobHandler->fail()`, as above)
        4.  Inside the `try...catch`, `Resque\Worker\ResqueWorker->perform()` triggers an
            `afterFork` event
        5.  Still inside the `try...catch`, `Resque\Worker\ResqueWorker->perform()` calls
            `Resque\JobHandler->perform()` with no arguments
        6.  `Resque\JobHandler->perform()` calls `Resque\JobHandler->getInstance()` with no
            arguments
        7.  If `Resque\JobHandler->getInstance()` has already been called, it returns
            the existing instance; otherwise:
        8.  `Resque\JobHandler->getInstance()` checks that the job's class (type)
            exists and has a `perform()` method; if not, in either case, it
            throws an exception which will be caught by
            `Resque\Worker\ResqueWorker->perform()`
        9.  `Resque\JobHandler->getInstance()` creates an instance of the job's class,
            and initializes it with a reference to the `Resque\JobHandler` itself, the
            job's arguments (which it gets by calling
            `Resque\JobHandler->getArguments()`, which in turn simply returns the value
            of `args[0]`, or an empty array if no arguments were passed), and
            the queue name
        10. `Resque\JobHandler->getInstance()` returns control, along with the job
            class instance, to `Resque\JobHandler->perform()`
        11. `Resque\JobHandler->perform()` sets up its own `try...catch` block to
            handle `Resque\Exceptions\DoNotPerformException` exceptions; any other exceptions are
            passed up to `Resque\Worker\ResqueWorker->perform()`
        12. `Resque\JobHandler->perform()` triggers a `beforePerform` event
        13. `Resque\JobHandler->perform()` calls `setUp()` on the instance, if it
            exists
        14. `Resque\JobHandler->perform()` calls `perform()` on the instance
        15. `Resque\JobHandler->perform()` calls `tearDown()` on the instance, if it
            exists
        16. `Resque\JobHandler->perform()` triggers an `afterPerform` event
        17. The `try...catch` block ends, suppressing `Resque\Exceptions\DoNotPerformException`
            exceptions by returning control, and the value `FALSE`, to
            `Resque\Worker\ResqueWorker->perform()`; any other situation returns the value
            `TRUE` along with control, instead
        18. The `try...catch` block in `Resque\Worker\ResqueWorker->perform()` ends
        19. `Resque\Worker\ResqueWorker->perform()` updates the job status from `RUNNING` to
            `COMPLETE`, then returns control, with no value, to the worker
            (again still in `Resque\Worker\ResqueWorker::work()`)
        20. `Resque\Job\PID()` is removed, the forked process will terminate soon
            cleanly
        21. `Resque\Worker\ResqueWorker::work()` calls `exit(0)` to terminate the job process
    -   SPECIAL CASE: Non-forking OS (Windows)
        1.  Same as the job above, except it doesn't call `exit(0)` when done
    7.  `Resque\Worker\ResqueWorker::work()` calls `Resque\Worker\ResqueWorker->doneWorking()` with no
        arguments
    8.  `Resque\Worker\ResqueWorker->doneWorking()` increments two processed counters in
        Redis: one for a total count, and one for the worker
    9.  `Resque\Worker\ResqueWorker->doneWorking()` deletes the Redis key set in
        `Resque\Worker\ResqueWorker->workingOn()`, then returns control, with no value, to
        `Resque\Worker\ResqueWorker::work()`

4.  `Resque\Worker\ResqueWorker::work()` returns control to the beginning of the main loop,
    where it will wait for the next job to become available, and start this
    process all over again
