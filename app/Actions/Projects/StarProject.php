<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class StarProject
{
    public function update(User $user, Project $project, array $input): void
    {
        $validated = Validator::make($input, [
            'is_starred' => ['required', 'boolean'],
        ])->validate();

        $membership = $user->projectMemberships()->where('project_id', $project->id)->firstOrFail();
        $membership->is_starred = $validated['is_starred'];
        $membership->save();
    }
}
