-- Oracle DDL input for the port.
--
-- Paste the REAL DDL here. Do not invent tables or columns.
-- Include, when available:
--   * CREATE TABLE with columns, types, defaults and NOT NULL;
--   * PRIMARY KEY, UNIQUE, FOREIGN KEY and CHECK constraints;
--   * indexes;
--   * sequences and identity columns;
--   * BEFORE/AFTER INSERT/UPDATE triggers that fill keys or audit columns;
--   * views and synonyms used by the module;
--   * table/column comments (they often contain the original labels).
--
-- The agent uses this file to generate migrations and to confirm table mappings.
-- It is input only: the agent must not execute it against a production database.

-- Example (replace with the real object):
CREATE TABLE orders (
    order_id      NUMBER(10)      NOT NULL,
    code          VARCHAR2(10)    NOT NULL,
    customer_id   NUMBER(10)      NOT NULL,
    status        CHAR(1)         DEFAULT 'N' NOT NULL,
    total         NUMBER(12, 2)   DEFAULT 0 NOT NULL,
    ordered_at    DATE            NOT NULL,
    notes         VARCHAR2(4000),
    created_by    VARCHAR2(30),
    created_at    DATE            DEFAULT SYSDATE,
    updated_by    VARCHAR2(30),
    updated_at    DATE,
    CONSTRAINT pk_orders PRIMARY KEY (order_id),
    CONSTRAINT uk_orders_code UNIQUE (code),
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers (customer_id),
    CONSTRAINT ck_orders_status CHECK (status IN ('N', 'A', 'P', 'C'))
);

CREATE INDEX ix_orders_status_date ON orders (status, ordered_at);

CREATE SEQUENCE seq_orders START WITH 1 INCREMENT BY 1;

CREATE OR REPLACE TRIGGER trg_orders_bi
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    IF :NEW.order_id IS NULL THEN
        SELECT seq_orders.NEXTVAL INTO :NEW.order_id FROM dual;
    END IF;
    :NEW.created_by := USER;
    :NEW.created_at := SYSDATE;
END;
/

COMMENT ON TABLE orders IS 'Pedidos';
COMMENT ON COLUMN orders.code IS 'Código';
