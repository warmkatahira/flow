<x-layouts.app title="グループ編集">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">グループ編集</h1>
    </x-slot:header>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form method="POST" action="{{ route('project-groups.update', $group) }}" class="space-y-5">
                @method('PUT')
                @include('project-groups.partials.form', ['submitLabel' => '更新する'])
            </form>
        </div>
    </div>
</x-layouts.app>
