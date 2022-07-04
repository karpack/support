<?php

namespace Karpack\Support\Traits;

use Closure;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;

trait HasTransactionAwareness
{
    /**
     * The processors to be executed after a transaction is commited
     * 
     * @var \Closure[]
     */
    protected $processors = [];

    /**
     * Flag that denotes whether the listeners where registered or not
     * 
     * @var boolean
     */
    protected $bootedListeners = false;

    /**
     * The given callback checks the current transaction status, and executes it accordingly.
     * 
     * @param \Closure $callback
     * @return void
     */
    public function afterTransactionCommitted(Closure $callback)
    {
        $currentLevel = DB::transactionLevel();

        if ($currentLevel === 0) {
            return $callback();
        }

        // This function is called inside a transaction, so add it to the processors list. 
        // We will process all these when we receive the transaction commited event. The 
        // processors are stored to ransaction levels. This is done to rollback the processors
        // when an error occurs. This is important or multiple transaction attempts will add 
        // the processors multiple times.
        $this->processors[$currentLevel][] = $callback;

        $this->registerTransactionListeners();
    }

    /**
     * Register the listener for transaction events. These listeners are executed and process
     * our callbacks accordingly.
     * 
     * @return bool
     */
    protected function registerTransactionListeners()
    {
        if ($this->bootedListeners) {
            return true;
        }

        $dispatcher = DB::getEventDispatcher();

        if (!isset($dispatcher)) {
            return false;
        }

        $this->registerTransactionRollbackListener();
        $this->registerTransactionCommitedListener();

        return $this->bootedListeners = true;
    }

    /**
     * Register the listener for the transaction commited events. A transaction commited
     * event can be raised even for inner transactions. So we don't begin processing until
     * we reach the outer transaction ie when the transaction level is zero. All the processors
     * are executed in sequence and an exception will cancel all the 
     * 
     * @return void
     */
    protected function registerTransactionCommitedListener()
    {
        DB::getEventDispatcher()->listen(TransactionCommitted::class, function () {
            $currentLevel = DB::transactionLevel();

            if ($currentLevel > 0 || empty($this->processors)) {
                return;
            }
            // Processors is a 2D array, so we'll flatten the processors to 1D array
            // and execute each of the callback in sequence. Moreover, these processors
            // could initiate other DB transactions, which could throw errors and cause
            // rollbacks. So it is required not to loop through the actual `$processors`
            $processors = collect($this->processors)->flatten(1);

            // We won't be capturing any errors in the processors as these processors could 
            // be possibly in sequence requiring every state to be fulfilled. We will clear
            // the current processors before processing as these processors themselves could 
            // initiate new transaction, and the level would be zero, and on commiting, this
            // listener will be triggered again which could lead to infinite loops. If we 
            // clear the processors before processing, this can be avoided.
            $this->processors = [];

            foreach ($processors as $processor) {
                $processor();
            }
        });
    }

    /**
     * Register the listener for the transaction rollback events. A transaction is rolled back when
     * an exception occurs within the transaction callback function. As Laravel transactions supports
     * multiple attempts of a transaction callback, a processor registered within the callback can be
     * registered again. To avoid this, we listen to the rollback event and removes all the processors 
     * registered in that level and upwards. So when the next attempt begins, the processors for the
     * rolled back level will be empty.
     * 
     * @return void
     */
    protected function registerTransactionRollbackListener()
    {
        DB::getEventDispatcher()->listen(TransactionRolledBack::class, function () {
            $currentLevel = DB::transactionLevel();

            foreach ($this->processors as $level => $processors) {
                // Since this event is called after the transaction level is updated, the current
                // level corresponds to the previous transaction. So we will remove all the processors
                // greater than the `$currentLevel`. 
                if ($level > $currentLevel) {
                    $this->processors[$level] = [];
                }
            }
        });
    }
}