<div class="space-y-6">
    <flux:heading size="xl">{{ __('Withdrawal Settings') }}</flux:heading>
    <flux:text>{{ __('Configure where you want to receive your funds.') }}</flux:text>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Bank Information') }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="beneficiary.name" label="{{ __('Account Holder Name') }}" />
                <flux:input wire:model="beneficiary.id" label="{{ __('National ID / Tax ID') }}" />
                
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="beneficiary.bank" label="{{ __('Bank Name') }}" />
                    <flux:input wire:model="beneficiary.account" label="{{ __('Account Number / IBAN') }}" />
                </div>

                <flux:select wire:model="beneficiary.type" label="{{ __('Account Type') }}">
                    <option value="SAVINGS">{{ __('Savings') }}</option>
                    <option value="CHECKING">{{ __('Checking') }}</option>
                </flux:select>
            </div>

            <div class="pt-4">
                <flux:button wire:click="save" variant="primary">{{ __('Save Bank Details') }}</flux:button>
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Regional Settings') }}</flux:heading>
            
            <div class="space-y-4">
                <flux:select wire:model="country" label="{{ __('Country') }}">
                    <option value="UY">Uruguay</option>
                    <option value="AR">Argentina</option>
                    <option value="BR">Brasil</option>
                    <option value="CL">Chile</option>
                    <option value="MX">México</option>
                    <option value="PA">Panamá</option>
                </flux:select>

                <flux:select wire:model="currency" label="{{ __('Currency') }}">
                    <option value="USD">USD</option>
                    <option value="UYU">UYU</option>
                    <option value="ARS">ARS</option>
                    <option value="BRL">BRL</option>
                    <option value="MXN">MXN</option>
                    <option value="USD">USD</option>
                </flux:select>

                <flux:select wire:model="method" label="{{ __('Payout Method') }}">
                    <option value="BANK_TRANSFER">{{ __('Bank Transfer') }}</option>
                    <option value="WALLET">{{ __('Wallet') }}</option>
                </flux:select>
            </div>
        </flux:card>
    </div>
</div>
