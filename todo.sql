-- RENAME TOKENS 
RENAME TABLE `craft_pmm`.`pmmpayments_tokens` TO `craft_pmm`.`pmmpayments_recurring`;

-- ADD relation column
ALTER TABLE `pmmpayments_payment` ADD `recurringId` INT(255) NULL AFTER `isRecurring`;

