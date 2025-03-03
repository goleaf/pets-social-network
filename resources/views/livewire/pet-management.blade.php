<div class="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
    <h1 class="text-2xl font-bold mb-4 text-center">{{ __('pets.manage_pets') }}</h1>
    <form wire:submit.prevent="save" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.name') }}</label>
            <input type="text" wire:model="name" class="w-full p-3 border rounded-lg">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.type') }}</label>
            <input type="text" wire:model="type" class="w-full p-3 border rounded-lg">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.breed') }}</label>
            <input type="text" wire:model="breed" class="w-full p-3 border rounded-lg">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.birthdate') }}</label>
            <input type="date" wire:model="birthdate" class="w-full p-3 border rounded-lg">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.location') }}</label>
            <input type="text" wire:model="location" class="w-full p-3 border rounded-lg" placeholder="{{ __('pets.location_placeholder') }}">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700 font-semibold mb-2">{{ __('pets.avatar') }}</label>
            <input type="file" wire:model="avatar" class="w-full p-2 border rounded-lg">
        </div>
        <button type="submit" class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600">{{ __('pets.add_pet') }}</button>
    </form>
    <h2 class="text-xl font-semibold mt-6">{{ __('pets.your_pets') }}</h2>
    @if ($pets->isEmpty())
        <p class="text-gray-500 text-center">{{ __('pets.no_pets') }}</p>
    @else
        <ul class="mt-2 grid grid-cols-1 gap-2">
            @foreach ($pets as $pet)
                <li class="flex flex-col sm:flex-row items-center justify-between">
                    <div class="text-center sm:text-left">
                        <a href="{{ route('pet.dashboard', $pet->id) }}" class="font-bold text-blue-500 hover:underline">{{ $pet->name }}</a>
                        @if ($pet->type) ({{ $pet->type }}, {{ $pet->breed }}) @endif
                        @if ($pet->location) <span class="text-gray-500"> - {{ $pet->location }}</span> @endif
                        <div class="flex space-x-2 mt-2 text-xs">
                            <a href="{{ route('pet.friends', $pet->id) }}" class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full hover:bg-blue-200 transition-colors duration-200">{{ __('pets.friends') }}</a>
                            <a href="{{ route('activity', ['entity_type' => 'pet', 'entity_id' => $pet->id]) }}" class="px-2 py-1 bg-green-100 text-green-700 rounded-full hover:bg-green-200 transition-colors duration-200">{{ __('pets.activity') }}</a>
                            <a href="{{ route('pet.posts', $pet->id) }}" class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full hover:bg-purple-200 transition-colors duration-200">{{ __('pets.posts') }}</a>
                        </div>
                        @if ($pet->avatar)
                            <img src="{{ Storage::url($pet->avatar) }}" class="w-12 h-12 rounded-full mt-2 mx-auto sm:ml-0">
                        @endif
                    </div>
                    <button wire:click="delete({{ $pet->id }})" class="text-red-500 hover:underline mt-2 sm:mt-0">{{ __('pets.delete_pet') }}</button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
