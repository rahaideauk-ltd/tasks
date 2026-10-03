<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectFact;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use App\Services\AiTaskGenerator;
use App\Services\ProjectAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->project = Project::create(['name' => 'Shop']);
    }

    public function test_admin_adds_facts_without_duplicates(): void
    {
        $this->post(route('admin.facts.store', $this->project), ['kind' => 'Keyword', 'value' => 'Buy Coffee'])->assertSessionHas('ok');
        $this->post(route('admin.facts.store', $this->project), ['kind' => 'keyword', 'value' => 'buy coffee'])->assertSessionHas('error');
        $this->post(route('admin.facts.store', $this->project), ['kind' => 'Target audience', 'value' => 'Students'])->assertSessionHas('ok');

        $this->assertSame(['keyword', 'target_audience'], $this->project->facts()->pluck('kind')->sort()->values()->all());
    }

    public function test_fact_status_can_be_changed(): void
    {
        $fact = $this->project->addFact('competitor', 'rival.com', null, 'ai', ProjectFact::STATUS_SUGGESTED);

        $this->put(route('admin.facts.update', $fact), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(ProjectFact::STATUS_CONFIRMED, $fact->fresh()->status);

        $this->put(route('admin.facts.update', $fact), ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    public function test_memory_edits_keep_revisions_and_can_be_restored(): void
    {
        $this->put(route('admin.projects.memory', $this->project), ['memory' => 'v1'])->assertSessionHas('ok');
        $this->put(route('admin.projects.memory', $this->project), ['memory' => 'v2']);
        $this->put(route('admin.projects.memory', $this->project), ['memory' => 'v2']); // unchanged: no new revision

        $this->assertSame(2, $this->project->memoryRevisions()->count());
        $first = $this->project->memoryRevisions()->reorder('id')->first();

        $this->post(route('admin.projects.memory.restore', [$this->project, $first]))->assertSessionHas('ok');
        $this->assertSame('v1', $this->project->fresh()->memory);

        $other = Project::create(['name' => 'Other']);
        $this->post(route('admin.projects.memory.restore', [$other, $first]))->assertNotFound();
    }

    public function test_internal_knowledge_is_not_shown_on_the_portal(): void
    {
        $this->project->forceFill(['brief' => 'SECRET BRIEF', 'memory' => 'SECRET MEMORY'])->save();
        $this->project->addFact('competitor', 'secret-rival.com');

        $this->get(route('portal.show', $this->project->token).'?lang=en')->assertOk()
            ->assertDontSee('SECRET BRIEF')->assertDontSee('SECRET MEMORY')->assertDontSee('secret-rival.com');
        $this->assertArrayNotHasKey('brief', $this->project->fresh()->toArray());
        $this->assertArrayNotHasKey('memory', $this->project->fresh()->toArray());
    }

    public function test_analysis_stores_ai_facts_memory_and_skill(): void
    {
        config(['services.anthropic.key' => 'test', 'tasks.auto_publish' => false]);
        Skill::create(['slug' => 'local-seo', 'name' => 'Global local SEO', 'instructions' => '...']);
        $own = Skill::create(['slug' => 'local-seo', 'name' => 'Our local SEO', 'instructions' => '...', 'project_id' => $this->project->id]);
        $this->project->addFact('competitor', 'old-rival.com', null, 'ai', ProjectFact::STATUS_REJECTED);

        $this->mock(AiTaskGenerator::class, fn ($m) => $m->shouldReceive('generate')->andReturn([
            'summary' => ['fa' => 'خلاصه', 'en' => 'Summary'],
            'memory' => "## Owner\n- Has no developer",
            'facts' => [
                ['kind' => 'keyword', 'value' => 'coffee beans', 'note' => 'GSC query'],
                ['kind' => 'competitor', 'value' => 'Old-Rival.com', 'note' => 'should stay rejected'],
            ],
            'tasks' => [[
                'source' => 'ai', 'priority' => 'high', 'reason' => 'r', 'skill' => 'local-seo',
                'category' => ['fa' => 'سئو', 'en' => 'SEO'], 'title' => ['fa' => 'گوگل مپ', 'en' => 'Claim Google Maps listing'],
                'description' => ['fa' => '...', 'en' => '...'],
            ]],
        ]));

        $result = app(ProjectAnalyzer::class)->analyze($this->project, useRules: false, useAi: true);
        $project = $this->project->fresh();

        $this->assertSame(1, $result['created']);
        $this->assertSame($own->id, Task::where('title_en', 'Claim Google Maps listing')->value('skill_id'));
        $this->assertSame("## Owner\n- Has no developer", $project->memory);
        $this->assertSame('ai', $project->memoryRevisions()->first()->source);
        $this->assertSame(1, $project->analysis['facts']);
        $this->assertSame(ProjectFact::STATUS_SUGGESTED, $project->facts()->where('value', 'coffee beans')->value('status'));
        $this->assertSame(1, $project->facts()->where('kind', 'competitor')->count());
    }

    public function test_admin_manages_skills(): void
    {
        $this->post(route('admin.skills.store'), ['name' => 'Local SEO', 'instructions' => 'Check the Google Business Profile', 'active' => 1])->assertSessionHasNoErrors();
        $skill = Skill::firstOrFail();
        $this->assertSame('local-seo', $skill->slug);
        $this->assertNull($skill->project_id);

        $this->post(route('admin.skills.store'), ['name' => 'Local SEO', 'instructions' => 'x'])->assertSessionHasErrors('slug');
        $this->post(route('admin.skills.store'), ['name' => 'Local SEO', 'instructions' => 'x', 'project_id' => $this->project->id])->assertSessionHasNoErrors();

        $this->put(route('admin.skills.update', $skill), ['name' => 'Local SEO', 'instructions' => 'y', 'active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($skill->fresh()->active);
        $this->assertSame(1, Skill::query()->availableFor($this->project)->count());

        $this->get(route('admin.skills.index'))->assertOk()->assertSee('Local SEO');
        $this->get(route('admin.projects.show', $this->project))->assertOk();
    }
}
