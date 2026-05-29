import { isMultiDbMode } from "./../config/index.js";
import { log } from "./../utils/index.js";
import SqlParser from "node-sql-parser";
const { Parser } = SqlParser;
const parser = new Parser();
function extractSchemaFromQuery(sql) {
    const defaultSchema = process.env.MYSQL_DB || null;
    if (defaultSchema && !isMultiDbMode) {
        return defaultSchema;
    }
    const useMatch = sql.match(/USE\s+`?([a-zA-Z0-9_]+)`?/i);
    if (useMatch && useMatch[1]) {
        return useMatch[1];
    }
    const dbTableMatch = sql.match(/`?([a-zA-Z0-9_]+)`?\.`?[a-zA-Z0-9_]+`?/i);
    if (dbTableMatch && dbTableMatch[1]) {
        return dbTableMatch[1];
    }
    return defaultSchema;
}
async function getQueryTypes(query) {
    try {
        log("info", "Parsing SQL query: ", query);
        const astOrArray = parser.astify(query, { database: "mysql" });
        const statements = Array.isArray(astOrArray) ? astOrArray : [astOrArray];
        return statements.map((stmt) => stmt.type?.toLowerCase() ?? "unknown");
    }
    catch (err) {
        log("error", "sqlParser error, query: ", query);
        log("error", "Error parsing SQL query:", err);
        throw new Error(`Parsing failed: ${err.message}`);
    }
}
export { extractSchemaFromQuery, getQueryTypes };
