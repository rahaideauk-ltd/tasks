<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function task(Project $project, string $status = Task::STATUS_TODO, int $round = 1): Task
    {
        return $project->tasks()->create(['title_fa' => 'تسک', 'title_en' => 'Task', 'status' => $status, 'round' => $round]);
    }

    public function test_portal_shows_published_tasks_but_not_drafts(): void
    {
        $project = Project::create(['name' => 'Shop']);
        $project->tasks()->create(['title_fa' => 'الف', 'title_en' => 'Visible task', 'status' => Task::STATUS_TODO, 'round' => 1]);
        $project->tasks()->create(['title_fa' => 'ب', 'title_en' => 'Secret draft', 'status' => Task::STATUS_DRAFT, 'round' => 0]);

        $this->get(route('portal.show', $project->token).'?lang=en')
            ->assertOk()->assertSee('Visible task')->assertDontSee('Secret draft');
    }

    public function test_client_marks_task_done(): void
    {
        $project = Project::create(['name' => 'Shop']);
        $task = $this->task($project);

        $this->post(route('portal.respond', [$project->token, $task]), ['done' => 1, 'note' => 'https://example.com/proof'])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(Task::STATUS_SUBMITTED, $task->status);
        $this->assertSame('https://example.com/proof', $task->client_note);
        $this->assertNotNull($task->responded_at);
    }

    public function test_not_done_requires_a_reason(): void
    {
        $project = Project::create(['name' => 'Shop']);
        $task = $this->task($project);

        $this->post(route('portal.respond', [$project->token, $task]), ['done' => 0, 'note' => ''])
            ->assertSessionHasErrors('note');
        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);

        $this->post(route('portal.respond', [$project->token, $task]), ['done' => 0, 'note' => 'No budget'])
            ->assertRedirect();
        $this->assertSame(Task::STATUS_NOT_DONE, $task->fresh()->status);
    }

    public function test_client_cannot_answer_drafts_other_projects_or_closed_tasks(): void
    {
        $project = Project::create(['name' => 'Shop']);
        $other = Project::create(['name' => 'Other']);

        $draft = $this->task($project, Task::STATUS_DRAFT, 0);
        $foreign = $this->task($other);
        $approved = $this->task($project, Task::STATUS_APPROVED);

        $this->post(route('portal.respond', [$project->token, $draft]), ['done' => 1])->assertNotFound();
        $this->post(route('portal.respond', [$project->token, $foreign]), ['done' => 1])->assertNotFound();
        $this->post(route('portal.respond', [$project->token, $approved]), ['done' => 1])->assertStatus(400);
    }

    public function test_stuck_analysis_does_not_keep_the_page_refreshing(): void
    {
        $project = Project::create(['name' => 'Shop', 'analysis_status' => 'running']);
        $this->assertTrue($project->isAnalysing());

        $this->travel(16)->minutes();
        $this->assertFalse($project->fresh()->isAnalysing());
        $this->get(route('portal.show', $project->token))->assertOk()->assertDontSee('http-equiv="refresh"', false);
    }
}
