<?php

namespace App\Http\Requests\Project;

use App\Enums\HolidayRule;
use App\Enums\RecurrenceFrequency;
use App\Models\Project;
use App\Models\RecurringTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 定期タスクの作成・更新（権限はコントローラで work を見る）
 */
class SaveRecurringTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function project(): Project
    {
        $recurring = $this->route('recurringTask');

        return $recurring instanceof RecurringTask ? $recurring->project : $this->route('project');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->project();
        $participant = Rule::in($project->users()->pluck('users.id')->all());
        $frequency = $this->enum('frequency', RecurrenceFrequency::class);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:60000'],
            'task_list_id' => ['required', 'integer', Rule::exists('task_lists', 'id')->where('project_id', $project->getKey())->whereNull('archived_at')],

            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'weekdays' => [Rule::requiredIf($frequency === RecurrenceFrequency::Weekly), 'nullable', 'array'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'month_day' => [Rule::requiredIf(in_array($frequency, [RecurrenceFrequency::MonthlyDay, RecurrenceFrequency::Yearly], true)), 'nullable', 'integer', 'between:1,31'],
            'month' => [Rule::requiredIf($frequency === RecurrenceFrequency::Yearly), 'nullable', 'integer', 'between:1,12'],
            'nth' => [Rule::requiredIf($frequency === RecurrenceFrequency::MonthlyNth), 'nullable', 'integer', 'between:1,5'],
            'nth_weekday' => [Rule::requiredIf($frequency === RecurrenceFrequency::MonthlyNth), 'nullable', 'integer', 'between:1,7'],
            'holiday_rule' => ['required', Rule::enum(HolidayRule::class)],

            'due_days' => ['nullable', 'integer', 'between:0,365'],
            // 作り始める日は今日以降（過去にすると、保存したとたんに過去の日付のタスクができてしまう）。直すときは今の値のままでもよい
            'starts_on' => ['required', 'date', $this->isMethod('PUT') ? 'date' : 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],

            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', Rule::exists('labels', 'id')->where('project_id', $project->getKey())],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', $participant],
            'follower_ids' => ['nullable', 'array'],
            'follower_ids.*' => ['integer', $participant],

            'checklists' => ['nullable', 'array', 'max:20'],
            'checklists.*.title' => ['required', 'string', 'max:255'],
            'checklists.*.items' => ['nullable', 'array', 'max:100'],
            'checklists.*.items.*.title' => ['required', 'string', 'max:255'],
            'checklists.*.items.*.assignee_id' => ['nullable', 'integer', $participant],
        ];
    }

    /**
     * 毎日・平日は休日の前後へずらせない（隣の回とぶつかるため）
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $frequency = $this->enum('frequency', RecurrenceFrequency::class);
            $rule = $this->enum('holiday_rule', HolidayRule::class);

            if ($frequency && $rule && ! $frequency->canShift() && $rule->shifts()) {
                $validator->errors()->add('holiday_rule', "{$frequency->label()}のときは、土日・祝日を前後へずらせません。");
            }
        }];
    }

    /**
     * 保存する値。繰り返し方に関係ない項目は空にしておく
     *
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $data = $this->validated();
        $frequency = RecurrenceFrequency::from($data['frequency']);

        $ids = fn (string $key) => collect($data[$key] ?? [])->map(fn ($id) => (int) $id)->unique()->values()->all();

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'task_list_id' => (int) $data['task_list_id'],
            'label_ids' => $ids('label_ids'),
            'assignee_ids' => $ids('assignee_ids'),
            'follower_ids' => $ids('follower_ids'),
            'checklists' => collect($data['checklists'] ?? [])->map(fn (array $checklist) => [
                'title' => $checklist['title'],
                'items' => collect($checklist['items'] ?? [])->map(fn (array $item) => [
                    'title' => $item['title'],
                    'assignee_id' => isset($item['assignee_id']) ? (int) $item['assignee_id'] : null,
                ])->values()->all(),
            ])->values()->all(),
            'frequency' => $frequency,
            'weekdays' => $frequency === RecurrenceFrequency::Weekly ? $ids('weekdays') : null,
            'month_day' => in_array($frequency, [RecurrenceFrequency::MonthlyDay, RecurrenceFrequency::Yearly], true) ? (int) $data['month_day'] : null,
            'month' => $frequency === RecurrenceFrequency::Yearly ? (int) $data['month'] : null,
            'nth' => $frequency === RecurrenceFrequency::MonthlyNth ? (int) $data['nth'] : null,
            'nth_weekday' => $frequency === RecurrenceFrequency::MonthlyNth ? (int) $data['nth_weekday'] : null,
            'holiday_rule' => HolidayRule::from($data['holiday_rule']),
            'due_days' => isset($data['due_days']) ? (int) $data['due_days'] : null,
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'description' => '説明',
            'task_list_id' => '入れるリスト',
            'frequency' => '繰り返し',
            'weekdays' => '曜日',
            'month_day' => '日',
            'month' => '月',
            'nth' => '第何週',
            'nth_weekday' => '曜日',
            'holiday_rule' => '土日・祝日の扱い',
            'due_days' => '期限',
            'starts_on' => '作り始める日',
            'ends_on' => '終わる日',
            'checklists.*.title' => 'チェックリストの名前',
            'checklists.*.items.*.title' => 'チェックリストの項目',
        ];
    }
}
