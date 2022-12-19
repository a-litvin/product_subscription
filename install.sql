CREATE TABLE IF NOT EXISTS PREFIX_subscription_periodicity
(
    id_periodicity INT(10) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `interval`     SMALLINT UNSIGNED NOT NULL,
    `name`         VARCHAR(64)       NOT NULL,
    created_at     DATETIME          NOT NULL,
    updated_at     DATETIME          NULL,
    id_old         INT UNSIGNED      NULL,
    PRIMARY KEY (id_periodicity)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_availability
(
    id_availability INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_product      INT UNSIGNED     NOT NULL,
    is_available    TINYINT(1)       NOT NULL,
    created_at      DATETIME         NOT NULL,
    updated_at      DATETIME         NULL,
    PRIMARY KEY (id_availability)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription
(
    id_subscription  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_customer      INT UNSIGNED     NOT NULL,
    id_periodicity   INT(10) UNSIGNED NOT NULL,
    id_cart          INT UNSIGNED     NOT NULL,
    next_delivery    DATE             NULL,
    `name`           VARCHAR(64)      NULL,
    customer_message VARCHAR(256)     NULL,
    is_active        TINYINT(1)       NOT NULL,
    is_deleted       TINYINT(1)       NOT NULL DEFAULT 0,
    created_at       DATETIME         NOT NULL,
    updated_at       DATETIME         NULL,
    id_old           INT UNSIGNED     NULL,
    PRIMARY KEY (id_subscription)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_cart_product
(
    id_subscription_cart_product INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_cart                      INT UNSIGNED     NOT NULL,
    id_product                   INT UNSIGNED     NOT NULL,
    id_product_attribute         INT UNSIGNED     NOT NULL,
    id_periodicity               INT UNSIGNED     NOT NULL,
    is_active                    TINYINT(1)       NOT NULL,
    created_at                   DATETIME         NOT NULL,
    updated_at                   DATETIME         NULL,
    PRIMARY KEY (id_subscription_cart_product)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_product
(
    id_subscription_product INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_subscription         INT(10) UNSIGNED NOT NULL,
    id_product              INT UNSIGNED     NOT NULL,
    id_product_attribute    INT UNSIGNED     NOT NULL,
    next_shipment_only      TINYINT(1)       NULL,
    skip_next_shipment_only TINYINT(1)       NULL,
    created_at              DATETIME         NOT NULL,
    updated_at              DATETIME         NULL,
    PRIMARY KEY (id_subscription_product)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_payment
(
    id_subscription_payment INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_subscription         INT(10) UNSIGNED NOT NULL,
    module_name             VARCHAR(128)     NOT NULL,
    id_vault                INT(10) UNSIGNED NOT NULL,
    created_at              DATETIME         NOT NULL,
    updated_at              DATETIME         NULL,
    PRIMARY KEY (id_subscription_payment)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_blocking_reason
(
    id_reason  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    handle     VARCHAR(64)      NOT NULL,
    `name`     VARCHAR(128)     NULL,
    created_at DATETIME         NOT NULL,
    PRIMARY KEY (id_reason)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_history
(
    id_subscription_history INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    id_order                INT UNSIGNED     NOT NULL,
    id_subscription         INT(10) UNSIGNED NOT NULL,
    created_at              DATETIME         NOT NULL,
    PRIMARY KEY (id_subscription_history)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE TABLE IF NOT EXISTS PREFIX_subscription_subscription_blocking_reason
(
    id_subscription INT(10) UNSIGNED NOT NULL,
    id_reason       INT(10) UNSIGNED NOT NULL,
    PRIMARY KEY (id_subscription, id_reason)
) ENGINE = ENGINE_TYPE
  DEFAULT CHARSET = UTF8;

CREATE INDEX INDEX_ID_PRODUCT
    ON PREFIX_subscription_availability (id_product);

ALTER TABLE PREFIX_subscription_periodicity
    ADD CONSTRAINT UNIQ_Interval UNIQUE (`interval`);

ALTER TABLE PREFIX_subscription_blocking_reason
    ADD CONSTRAINT UNIQ_Handle UNIQUE (handle);

ALTER TABLE PREFIX_subscription_availability
    ADD CONSTRAINT UNIQ_ProductId UNIQUE (id_product);

ALTER TABLE PREFIX_subscription
    ADD CONSTRAINT UNIQ_Subscription_Periodicity UNIQUE (id_subscription, id_periodicity);

ALTER TABLE PREFIX_subscription_cart_product
    ADD CONSTRAINT UNIQ_Cart_Product_ProductAttribute UNIQUE (id_cart, id_product, id_product_attribute);

ALTER TABLE PREFIX_subscription_product
    ADD CONSTRAINT UNIQ_Subscription_Product_ProductAttribute UNIQUE (id_subscription, id_product, id_product_attribute);

ALTER TABLE PREFIX_subscription
    ADD CONSTRAINT UNIQ_OldId UNIQUE (id_old);

ALTER TABLE PREFIX_subscription_periodicity
    ADD CONSTRAINT UNIQ_OldId UNIQUE (id_old);

ALTER TABLE PREFIX_subscription
    ADD CONSTRAINT FK_Subscription_SubscriptionPeriodicity FOREIGN KEY (id_periodicity) REFERENCES PREFIX_subscription_periodicity (id_periodicity);

ALTER TABLE PREFIX_subscription_cart_product
    ADD CONSTRAINT FK_SubscriptionCartProduct_SubscriptionPeriodicity FOREIGN KEY (id_periodicity) REFERENCES PREFIX_subscription_periodicity (id_periodicity);

ALTER TABLE PREFIX_subscription_payment
    ADD CONSTRAINT FK_SubscriptionPayment_Subscription FOREIGN KEY (id_subscription) REFERENCES PREFIX_subscription (id_subscription);

ALTER TABLE PREFIX_subscription_subscription_blocking_reason
    ADD CONSTRAINT FK_SubscriptionReason_Subscription FOREIGN KEY (id_subscription) REFERENCES PREFIX_subscription (id_subscription);

ALTER TABLE PREFIX_subscription_subscription_blocking_reason
    ADD CONSTRAINT FK_SubscriptionReason_Reason FOREIGN KEY (id_reason) REFERENCES PREFIX_subscription_blocking_reason (id_reason);

ALTER TABLE PREFIX_subscription_product
    ADD CONSTRAINT FK_SubscriptionProduct_Subscription FOREIGN KEY (id_subscription) REFERENCES PREFIX_subscription (id_subscription);

ALTER TABLE PREFIX_subscription_product
    ADD CONSTRAINT FK_SubscriptionProduct_SubscriptionAvailability FOREIGN KEY (id_product) REFERENCES PREFIX_subscription_availability (id_product);

ALTER TABLE PREFIX_subscription_history
    ADD CONSTRAINT FK_SubscriptionHistory_Subscription FOREIGN KEY (id_subscription) REFERENCES PREFIX_subscription (id_subscription);
