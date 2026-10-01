<div x-data="{ show: false, msg: '' }"
     @toast.window="msg = $event.detail.mensaje; show = true; setTimeout(() => show = false, 3000)"
     x-cloak
     class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2">
    <div x-show="show" x-transition
         class="rounded-full bg-stone-900 px-5 py-2 text-sm text-white shadow-lg"
         x-text="msg"></div>
</div>
