<x-layouts.app title="プロジェクト作成">
    <x-slot:header>
        <h1 class="text-lg font-bold text-slate-800">プロジェクト作成</h1>
    </x-slot:header>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-xl shadow-sm px-6 py-8">
            <form method="POST" action="{{ route('projects.store') }}" enctype="multipart/form-data" class="space-y-6">
                @include('projects.partials.form', ['project' => null, 'submitLabel' => '作成する'])
            </form>
        </div>
    </div>
</x-layouts.app>
