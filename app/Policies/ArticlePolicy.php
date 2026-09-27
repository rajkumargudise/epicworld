<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

/**
 * Authorization for the editorial actions the CMS exposes on an
 * Article. Two tiers, not a full RBAC framework: any editor may view
 * and edit content and move it into Scheduled (approve); only an
 * admin may publish or clear a sensitive-content review - the two
 * actions with the least room for a mistake to be undone. These
 * methods are the only place that decides who may call
 * PublicationPolicy - the controllers never check roles themselves.
 */
class ArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Article $article): bool
    {
        return $user->isEditor();
    }

    public function update(User $user, Article $article): bool
    {
        return $user->isEditor();
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->isEditor();
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }

    public function confirmSensitiveReview(User $user, Article $article): bool
    {
        return $user->isAdmin();
    }
}
