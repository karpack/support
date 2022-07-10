<?php

namespace Karpack\Support\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasModelRestrictions
{
    /**
     * Performs an auth check to see whether the given `$user` (or authenticated user) has 
     * capabilities to perform the given `$task` on the model. Throws authorization exception 
     * if user is no authority to perform the task
     *   
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string|null $task
     * @param \App\Model|null $user
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorizeUserToPerformTaskOn(Model $model, $task = null, $user = null)
    {
        if (!$this->userCanPerformTaskOn($model, $task, $user)) {
            throw new AuthorizationException();
        }
    }

    /**
     * Check whether the given user can perform the given task on the model. If no user
     * is provided, capabilities of authenticated user is put into test. 
     * 
     * A user can access the model, if it belongs to the user itself or if the user has 
     * necessary permission to access it.
     * 
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string|null $task
     * @param \Illuminate\Database\Eloquent\Model|null $user
     * @return bool
     */
    public function userCanPerformTaskOn(Model $model, $task = null, $user = null)
    {
        if (is_null($user)) {
            $user = Auth::user();
        }
        if ($user instanceof Model) {
            // If the model is user itself, then the primary key of the accessing user
            // and the accessed user model should be same.
            if (get_class($user) === get_class($model)) {
                return $user->getKey() === $model->getKey();
            }
            // If it's any other model, then the foreign key user_id should match the 
            // accessing user id.
            return $user->getKey() === $model->user_id;
        }
        return $task && $user instanceof Authorizable && $user->can($task);
    }
}
