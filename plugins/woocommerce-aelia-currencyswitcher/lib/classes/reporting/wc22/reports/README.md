#This folder is intentionally left empty

## Changes in WooCommerce 2.2 reports
Most reports in WC2.2 are unchanged. The report overrides found in `WC21/reports` folder are loaded automatically by `Aelia\CurrencySwitcher\WC22\Reports` class, and can be reused as they are.

Class `Aelia\CurrencySwitcher\WC22\Reports` just implements some new logic for the dashboard widgets, which use different queries.
