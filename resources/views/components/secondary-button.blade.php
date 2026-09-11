<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn btn-outline focus-visible:ring-sky-600 focus-visible:ring-offset-2']) }}>
    {{ $slot }}
</button>
