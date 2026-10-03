<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeProject;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AdminTaskTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->project = Project::create(['name' => 'Shop']);
    }

    protected function task(string $status, int $round = 1): Task
    {
        return $this->project->tasks()->create(['title_fa' => 'تسک', 'title_en' => 'Task '.uniqid(), 'status' => $status, 'round' => $round]);
    }

    public function test_admin_pages_require_login(): void
    {
        auth()->logout();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_approve_and_reject_submitted_task(): void
    {
        $a = $this->task(Task::STATUS_SUBMITTED);
        $b = $this->task(Task::STATUS_SUBMITTED);

        $this->post(route('admin.tasks.review', $a), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->post(route('admin.tasks.review', $b), ['decision' => 'reject', 'feedback' => 'Link is broken'])->assertSessionHasNoErrors();

        $this->assertSame(Task::STATUS_APPROVED, $a->fresh()->status);
        $this->assertSame(Task::STATUS_REJECTED, $b->fresh()->status);
        $this->assertSame('Link is broken', $b->fresh()->admin_feedback);
    }

    public function test_review_decisions_respect_current_status(): void
    {
        $draft = $this->task(Task::STATUS_DRAFT, 0);
        $todo = $this->task(Task::STATUS_TODO);

        $this->post(route('admin.tasks.review', $draft), ['decision' => 'approve'])->assertSessionHasErrors('decision');
        $this->post(route('admin.tasks.review', $todo), ['decision' => 'publish'])->assertSessionHasErrors('decision');

        $this->assertSame(Task::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(0, $draft->fresh()->round);
        $this->assertSame(1, $todo->fresh()->round);
    }

    public function test_reopen_not_done_task(): void
    {
        $task = $this->task(Task::STATUS_NOT_DONE);

        $this->post(route('admin.tasks.review', $task), ['decision' => 'reopen', 'feedback' => 'Try the free plan'])->assertSessionHasNoErrors();

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
    }

    public function test_publish_moves_drafts_into_next_round(): void
    {
        $this->task(Task::STATUS_APPROVED, 1);
        $d1 = $this->task(Task::STATUS_DRAFT, 0);
        $d2 = $this->task(Task::STATUS_DRAFT, 0);

        $this->post(route('admin.projects.publish', $this->project))->assertSessionHas('ok');

        foreach ([$d1, $d2] as $d) {
            $this->assertSame(Task::STATUS_TODO, $d->fresh()->status);
            $this->assertSame(2, $d->fresh()->round);
        }
        $this->post(route('admin.projects.publish', $this->project))->assertSessionHas('error');
    }

    public function test_analyze_is_not_started_twice(): void
    {
        Bus::fake();
        $this->project->forceFill(['analysis_status' => 'running'])->save();

        $this->post(route('admin.projects.analyze', $this->project))->assertSessionHas('error');
        Bus::assertNotDispatched(AnalyzeProject::class);

        $this->project->forceFill(['analysis_status' => 'done'])->save();
        $this->post(route('admin.projects.analyze', $this->project))->assertSessionHas('ok');
        Bus::assertDispatched(AnalyzeProject::class);
    }
}
