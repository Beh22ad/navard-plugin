<?php

namespace Navard\Shortcode;

if (! defined('ABSPATH')) {
    exit;
}

final class TaxToggle
{
    public function hooks(): void
    {
        add_shortcode('navard_tax', [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'navard-tax-toggle',
            NAVARD_URL . 'assets/css/tax-toggle.css',
            [],
            NAVARD_VERSION
        );

        wp_register_script(
            'navard-tax-toggle',
            '',
            [],
            NAVARD_VERSION,
            true
        );

        wp_add_inline_script('navard-tax-toggle', $this->get_js());
    }

    public function render($atts): string
    {
        wp_enqueue_style('navard-tax-toggle');
        wp_enqueue_script('navard-tax-toggle');

        $atts = shortcode_atts([
            'tax' => '10',
        ], $atts, 'navard_tax');

        $tax_rate = $this->get_tax_rate((float) $atts['tax']);

        if ($tax_rate <= 0) {
            return '';
        }

        ob_start();
?>
        <div class="navard-tax-widget">
            <div class="navard-tax-switch">
                <label class="navard-tax-label" for="navard-tax-toggle-checkbox">قیمت با ارزش افزوده</label>
                <input type="checkbox" class="navard-tax-checkbox" id="navard-tax-toggle-checkbox"
                    data-tax-rate="<?php echo esc_attr($tax_rate); ?>">
            </div>
        </div>
<?php
        return (string) ob_get_clean();
    }

    private function get_tax_rate(float $fallback_tax): float
    {
        if ('yes' === get_option('woocommerce_calc_taxes')) {
            $tax_rates = \WC_Tax::get_rates();

            if (! empty($tax_rates)) {
                $rate = reset($tax_rates);

                if (isset($rate['rate']) && (float) $rate['rate'] > 0) {
                    return (float) $rate['rate'];
                }
            }
        }

        return $fallback_tax > 0 ? $fallback_tax : 0;
    }

    private function get_js(): string
    {
        return "
        document.addEventListener('DOMContentLoaded', function () {
            const checkbox = document.getElementById('navard-tax-toggle-checkbox');

            if (!checkbox) {
                return;
            }

            const taxRate = parseFloat(
                checkbox.getAttribute('data-tax-rate')
            ) || 0;

            const multiplier = 1 + (taxRate / 100);

            checkbox.addEventListener('change', function () {
                const priceCells = document.querySelectorAll('.navard-sc-td-price');

                priceCells.forEach(td => {
                    if (td.textContent.includes('تماس')) {
                        return;
                    }

                    if (!td.dataset.basePrice) {
                        const cleanPrice = td.textContent
                            .replace(/,/g, '')
                            .trim();

                        if (!cleanPrice) {
                            return;
                        }

                        td.dataset.basePrice = cleanPrice;
                    }

                    const basePrice = parseFloat(td.dataset.basePrice);

                    if (isNaN(basePrice)) {
                        return;
                    }

                    if (checkbox.checked) {
                        const newPrice = Math.round(basePrice * multiplier);
                        td.textContent = newPrice.toLocaleString('en-US');
                    } else {
                        td.textContent = basePrice.toLocaleString('en-US');
                    }
                });
            });
        });
        ";
    }
}
