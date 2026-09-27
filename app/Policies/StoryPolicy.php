<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

/**
 * Read access to the editorial Story queue and its evidence - any
 * editor or admin. Nothing about a Story is writable through the CMS
 * yet (that belongs to its Article), so there is nothing beyond
 * viewing to authorize here.
 */
class StoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Story $story): bool
    {
        return $user->isEditor();
    }
}
