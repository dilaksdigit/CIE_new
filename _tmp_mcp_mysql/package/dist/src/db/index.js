import { performance } from "perf_hooks";
import { isMultiDbMode } from "./../config/index.js";
import { isDDLAllowedForSchema, isInsertAllowedForSchema, isUpdateAllowedForSchema, isDeleteAllowedForSchema, } from "./permissions.js";
import { extractSchemaFromQuery, getQueryTypes } from "./utils.js";
import * as mysql2 from "mysql2/promise";
import { log } from "./../utils/index.js";
import { mcpConfig as config, MYSQL_DISABLE_READ_ONLY_TRANSACTIONS } from "./../config/index.js";
if (isMultiDbMode && process.env.MULTI_DB_WRITE_MODE !== "true") {
    log("error", "Multi-DB mode detected - enabling read-only mode for safety");
}
const isTestEnvironment = process.env.NODE_ENV === "test" || process.env.VITEST;
function safeExit(code) {
    if (!isTestEnvironment) {
        process.exit(code);
    }
    else {
        log("error", `[Test mode] Would have called process.exit(${code})`);
    }
}
let poolPromise;
const getPool = () => {
    if (!poolPromise) {
        poolPromise = new Promise((resolve, reject) => {
            try {
                const pool = mysql2.createPool(config.mysql);
                log("info", "MySQL pool created successfully");
                resolve(pool);
            }
            catch (error) {
                log("error", "Error creating MySQL pool:", error);
                reject(error);
            }
        });
    }
    return poolPromise;
};
async function executeQuery(sql, params = []) {
    let connection;
    try {
        const pool = await getPool();
        connection = await pool.getConnection();
        const result = await connection.query(sql, params);
        return (Array.isArray(result) ? result[0] : result);
    }
    catch (error) {
        log("error", "Error executing query:", error);
        throw error;
    }
    finally {
        if (connection) {
            connection.release();
            log("error", "Connection released");
        }
    }
}
async function executeWriteQuery(sql) {
    let connection;
    try {
        const pool = await getPool();
        connection = await pool.getConnection();
        log("error", "Write connection acquired");
        const schema = extractSchemaFromQuery(sql);
        await connection.beginTransaction();
        try {
            const startTime = performance.now();
            const result = await connection.query(sql);
            const endTime = performance.now();
            const duration = endTime - startTime;
            const response = Array.isArray(result) ? result[0] : result;
            await connection.commit();
            let responseText;
            const queryTypes = await getQueryTypes(sql);
            const isUpdateOperation = queryTypes.some((type) => ["update"].includes(type));
            const isInsertOperation = queryTypes.some((type) => ["insert"].includes(type));
            const isDeleteOperation = queryTypes.some((type) => ["delete"].includes(type));
            const isDDLOperation = queryTypes.some((type) => ["create", "alter", "drop", "truncate"].includes(type));
            if (isInsertOperation) {
                const resultHeader = response;
                responseText = `Insert successful on schema '${schema || "default"}'. Affected rows: ${resultHeader.affectedRows}, Last insert ID: ${resultHeader.insertId}`;
            }
            else if (isUpdateOperation) {
                const resultHeader = response;
                responseText = `Update successful on schema '${schema || "default"}'. Affected rows: ${resultHeader.affectedRows}, Changed rows: ${resultHeader.changedRows || 0}`;
            }
            else if (isDeleteOperation) {
                const resultHeader = response;
                responseText = `Delete successful on schema '${schema || "default"}'. Affected rows: ${resultHeader.affectedRows}`;
            }
            else if (isDDLOperation) {
                responseText = `DDL operation successful on schema '${schema || "default"}'.`;
            }
            else {
                responseText = JSON.stringify(response, null, 2);
            }
            return {
                content: [
                    {
                        type: "text",
                        text: responseText,
                    },
                    {
                        type: "text",
                        text: `Query execution time: ${duration.toFixed(2)} ms`,
                    },
                ],
                isError: false,
            };
        }
        catch (error) {
            log("error", "Error executing write query:", error);
            await connection.rollback();
            return {
                content: [
                    {
                        type: "text",
                        text: `Error executing write operation: ${error instanceof Error ? error.message : String(error)}`,
                    },
                ],
                isError: true,
            };
        }
    }
    catch (error) {
        log("error", "Error in write operation transaction:", error);
        return {
            content: [
                {
                    type: "text",
                    text: `Database connection error: ${error instanceof Error ? error.message : String(error)}`,
                },
            ],
            isError: true,
        };
    }
    finally {
        if (connection) {
            connection.release();
            log("error", "Write connection released");
        }
    }
}
async function executeReadOnlyQuery(sql) {
    let connection;
    try {
        const queryTypes = await getQueryTypes(sql);
        const schema = extractSchemaFromQuery(sql);
        const isUpdateOperation = queryTypes.some((type) => ["update"].includes(type));
        const isInsertOperation = queryTypes.some((type) => ["insert"].includes(type));
        const isDeleteOperation = queryTypes.some((type) => ["delete"].includes(type));
        const isDDLOperation = queryTypes.some((type) => ["create", "alter", "drop", "truncate"].includes(type));
        if (isInsertOperation && !isInsertAllowedForSchema(schema)) {
            log("error", `INSERT operations are not allowed for schema '${schema || "default"}'. Configure SCHEMA_INSERT_PERMISSIONS.`);
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: INSERT operations are not allowed for schema '${schema || "default"}'. Ask the administrator to update SCHEMA_INSERT_PERMISSIONS.`,
                    },
                ],
                isError: true,
            };
        }
        if (isUpdateOperation && !isUpdateAllowedForSchema(schema)) {
            log("error", `UPDATE operations are not allowed for schema '${schema || "default"}'. Configure SCHEMA_UPDATE_PERMISSIONS.`);
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: UPDATE operations are not allowed for schema '${schema || "default"}'. Ask the administrator to update SCHEMA_UPDATE_PERMISSIONS.`,
                    },
                ],
                isError: true,
            };
        }
        if (isDeleteOperation && !isDeleteAllowedForSchema(schema)) {
            log("error", `DELETE operations are not allowed for schema '${schema || "default"}'. Configure SCHEMA_DELETE_PERMISSIONS.`);
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: DELETE operations are not allowed for schema '${schema || "default"}'. Ask the administrator to update SCHEMA_DELETE_PERMISSIONS.`,
                    },
                ],
                isError: true,
            };
        }
        if (isDDLOperation && !isDDLAllowedForSchema(schema)) {
            log("error", `DDL operations are not allowed for schema '${schema || "default"}'. Configure SCHEMA_DDL_PERMISSIONS.`);
            return {
                content: [
                    {
                        type: "text",
                        text: `Error: DDL operations are not allowed for schema '${schema || "default"}'. Ask the administrator to update SCHEMA_DDL_PERMISSIONS.`,
                    },
                ],
                isError: true,
            };
        }
        if ((isInsertOperation && isInsertAllowedForSchema(schema)) ||
            (isUpdateOperation && isUpdateAllowedForSchema(schema)) ||
            (isDeleteOperation && isDeleteAllowedForSchema(schema)) ||
            (isDDLOperation && isDDLAllowedForSchema(schema))) {
            return executeWriteQuery(sql);
        }
        const pool = await getPool();
        connection = await pool.getConnection();
        log("error", "Read-only connection acquired");
        if (!MYSQL_DISABLE_READ_ONLY_TRANSACTIONS) {
            await connection.query("SET SESSION TRANSACTION READ ONLY");
        }
        else {
            log("info", "Read-only transactions disabled via MYSQL_DISABLE_READ_ONLY_TRANSACTIONS=true");
        }
        await connection.beginTransaction();
        try {
            const startTime = performance.now();
            const result = await connection.query(sql);
            const endTime = performance.now();
            const duration = endTime - startTime;
            const rows = Array.isArray(result) ? result[0] : result;
            await connection.rollback();
            if (!MYSQL_DISABLE_READ_ONLY_TRANSACTIONS) {
                await connection.query("SET SESSION TRANSACTION READ WRITE");
            }
            return {
                content: [
                    {
                        type: "text",
                        text: JSON.stringify(rows, null, 2),
                    },
                    {
                        type: "text",
                        text: `Query execution time: ${duration.toFixed(2)} ms`,
                    },
                ],
                isError: false,
            };
        }
        catch (error) {
            log("error", "Error executing read-only query:", error);
            await connection.rollback();
            throw error;
        }
    }
    catch (error) {
        log("error", "Error in read-only query transaction:", error);
        try {
            if (connection) {
                await connection.rollback();
                if (!MYSQL_DISABLE_READ_ONLY_TRANSACTIONS) {
                    await connection.query("SET SESSION TRANSACTION READ WRITE");
                }
            }
        }
        catch (cleanupError) {
            log("error", "Error during cleanup:", cleanupError);
        }
        throw error;
    }
    finally {
        if (connection) {
            connection.release();
            log("error", "Read-only connection released");
        }
    }
}
export { isTestEnvironment, safeExit, executeQuery, getPool, executeWriteQuery, executeReadOnlyQuery, poolPromise, };
