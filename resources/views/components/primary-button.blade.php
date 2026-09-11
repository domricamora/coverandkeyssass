<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-dark focus-visible:ring-sky-600 focus-visible:ring-offset-2']) }}>
    {{ $slot }}
</button>
