<?php

namespace App\Actions\GithubApp;

use App\Models\SourceControl;

class EditGithubAppSourceControl
{
    public function edit(SourceControl $sourceControl, array $input, ?int $projectId): SourceControl
    {
        $sourceControl->project_id = $projectId;

        $sourceControl->save();

        return $sourceControl;
    }
}
