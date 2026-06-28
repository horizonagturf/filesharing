<div x-cloak x-show="step == 1">
    <x-ui.page-header :title="__('app.upload-settings')" />

    <div class="space-y-4">
        <x-ui.input
            id="upload-title"
            type="text"
            name="title"
            maxlength="70"
            :label="__('app.upload-title')"
            required
            x-model="bundle.title"
        />

        <div>
            <x-ui.label for="upload-description">@lang('app.upload-description')</x-ui.label>
            <textarea
                id="upload-description"
                name="description"
                maxlength="300"
                class="mt-1"
            ></textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.select id="upload-expiry" name="expiry" :label="__('app.upload-expiry')" required x-model="bundle.expiry">
                <option value="forever">@lang('app.forever')</option>
                @foreach (config('sharing.expiry_values') as $k => $e)
                    <option value="{{ Upload::getExpirySeconds($k) }}">@lang('app.'.$e)</option>
                @endforeach
            </x-ui.select>

            <x-ui.input
                id="upload-max-downloads"
                type="number"
                name="max_downloads"
                min="0"
                max="999"
                :label="__('app.max-downloads')"
                x-model="bundle.max_downloads"
            />

            <x-ui.input
                id="upload-password"
                type="text"
                name="password"
                :label="__('app.bundle-password')"
                :placeholder="__('app.leave-empty')"
                x-model="bundle.password"
            />
        </div>

        @include('upload._share-mode')
        @include('upload._recipients')
    </div>

    <div class="mt-8 flex justify-end">
        <x-ui.button variant="primary" icon="chevron-right" x-on:click="uploadStep()">
            @lang('app.start-uploading')
        </x-ui.button>
    </div>
</div>
