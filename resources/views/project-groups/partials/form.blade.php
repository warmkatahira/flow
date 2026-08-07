@csrf

<div>
    <x-input-label for="name" value="グループ名" :required="true" />
    <x-text-input id="name" name="name" type="text" :value="old('name', $group?->name)"
                  required autofocus maxlength="255" />
    <x-input-error :messages="$errors->get('name')" />
</div>

<div class="flex items-center gap-3 pt-2">
    <x-primary-button>{{ $submitLabel }}</x-primary-button>
    <a href="{{ route('project-groups.index') }}" class="text-sm text-slate-600 hover:text-slate-900">キャンセル</a>
</div>
