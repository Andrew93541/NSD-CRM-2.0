<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\Security\TenantContext;
use Illuminate\Support\Facades\Schema;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentCompany() !== null;
    }

    public function view(User $user, Project $project): bool
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId || (int) $project->company_id !== (int) $companyId) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            return true;
        }

        if ((int) $project->manager_id === (int) $user->id) {
            return true;
        }

        // Site manager or supervisor can view a project if they are assigned to at least one site of that project
        $accessibleSiteIds = $user->accessibleSiteIds();
        if (empty($accessibleSiteIds)) {
            return false;
        }

        if ($project->sites()->whereIn('sites.id', $accessibleSiteIds)->exists()) {
            return true;
        }

        if (Schema::hasTable('project_sites') && $project->linkedSites()->whereIn('sites.id', $accessibleSiteIds)->exists()) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create')
            || $user->hasPermission('projects.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
    }

    public function update(User $user, Project $project): bool
    {
        if (!$this->view($user, $project)) {
            return false;
        }

        return $user->hasPermission('projects.update')
            || $user->hasPermission('projects.manage')
            || (int) $project->manager_id === (int) $user->id
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
    }

    public function manageMilestones(User $user, Project $project): bool
    {
        if (!$this->view($user, $project)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || (int) $project->manager_id === (int) $user->id
            || $user->hasPermission('projects.manage');
    }

    public function uploadDocument(User $user, Project $project): bool
    {
        return $this->manageMilestones($user, $project);
    }

    public function downloadDocument(User $user, Project $project, ProjectDocument $document): bool
    {
        if ((int) $document->project_id !== (int) $project->id || (int) $document->company_id !== (int) $project->company_id) {
            return false;
        }

        if (!$this->view($user, $project)) {
            return false;
        }

        return $document->isVisibleTo($user);
    }

    public function deleteDocument(User $user, Project $project, ProjectDocument $document): bool
    {
        if ((int) $document->project_id !== (int) $project->id || (int) $document->company_id !== (int) $project->company_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            || (int) $project->manager_id === (int) $user->id;
    }
}

