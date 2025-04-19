<?php

use core\setting\Setting;

// === sale.features ===
Setting::assert_value('sale', 'features', 'booking.channel_manager', false);
Setting::assert_value('sale', 'features', 'booking.instant_payment', false);
Setting::assert_value('sale', 'features', 'booking.has_activity', false);
Setting::assert_value('sale', 'features', 'payment.bank_check', false);
Setting::assert_value('sale', 'features', 'payment.financial_help', false);
Setting::assert_value('sale', 'features', 'booking.services.store.folded', false);
Setting::assert_value('sale', 'features', 'booking.services.identification.folded', false);
Setting::assert_value('sale', 'features', 'booking.services.products.folded', false);
Setting::assert_value('sale', 'features', 'booking.services.activities.folded', false);
Setting::assert_value('sale', 'features', 'booking.services.accommodations.folded', false);
Setting::assert_value('sale', 'features', 'booking.services.meals.folded', false);
Setting::assert_value('sale', 'features', 'customer.number_assignment', 'account number');
Setting::assert_value('sale', 'features', 'templates.quote.consumption_table.show', false);
Setting::assert_value('sale', 'features', 'templates.quote.activities.show', false);

// === sale.organization ===
Setting::assert_value('sale', 'organization', 'booking.channel_manager.client_domain');

// === finance.accounting ===
Setting::assert_value('finance', 'accounting', 'invoice.export_type');
Setting::assert_value('finance', 'accounting', 'account.sales');
Setting::assert_value('finance', 'accounting', 'account.sales_taxes');
Setting::assert_value('finance', 'accounting', 'account.trade_debtors');

// === identity.locale ===
Setting::assert_value('identity', 'locale', 'account.prefix');
Setting::assert_value('identity', 'locale', 'account.sequence');
Setting::assert_value('identity', 'locale', 'account.sequence_format');
