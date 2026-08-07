<x-layouts.app title="グループ作成">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">グループ作成</h1>
    </x-slot:header>

    <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form autocomplete="off" method="POST" action="{{ route('project-groups.store') }}" class="space-y-5">
                @include('project-groups.partials.form', ['group' => null, 'submitLabel' => '作成する'])
            </form>
        </div>
    </div>
</x-layouts.app>
