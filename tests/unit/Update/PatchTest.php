<?php
namespace Tests\Unit\Update;

use HHK\Update\Patch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Patch::class)]
class PatchTest extends TestCase
{
    public function testStatementsAreSplitOnSemicolons(): void
    {
        $sql = "CREATE TABLE a (id INT);\nCREATE TABLE b (id INT);";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('CREATE TABLE a', $statements[0]);
        $this->assertStringContainsString('CREATE TABLE b', $statements[1]);
    }

    /**
     * The exact live bug: a '--' comment containing a ';' used to be cut in half by a plain explode(';', ...),
     * corrupting both the statement the comment was attached to and whatever followed.
     */
    public function testASemicolonInsideALineCommentDoesNotSplit(): void
    {
        $sql = "CREATE TABLE a (\n"
            . "    id INT,\n"
            . "    -- several fields; everything else holds one value\n"
            . "    PRIMARY KEY (id)\n"
            . ") ENGINE = InnoDB;\n"
            . "CREATE TABLE b (id INT);";

        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements, 'the comment\'s own ; must not count as a statement boundary');
        $this->assertStringContainsString('PRIMARY KEY', $statements[0]);
        $this->assertStringContainsString('ENGINE = InnoDB', $statements[0]);
        $this->assertStringContainsString('CREATE TABLE b', $statements[1]);
    }

    public function testASemicolonInAHashCommentDoesNotSplit(): void
    {
        // the comment (and its own ';') comes after the real statement-ending ';', so it attaches to the next
        // statement - the same grouping a plain explode(';', ...) would have produced
        $sql = "SELECT 1; # a comment; with a semicolon\nSELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('# a comment; with a semicolon', $statements[1]);
    }

    public function testASemicolonInABlockCommentDoesNotSplit(): void
    {
        $sql = "SELECT 1 /* one ; two ; three */;\nSELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('one ; two ; three', $statements[0]);
    }

    public function testASemicolonInAQuotedStringDoesNotSplit(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('a;b');\nINSERT INTO t (v) VALUES (\"c;d\");";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'a;b'", $statements[0]);
        $this->assertStringContainsString('"c;d"', $statements[1]);
    }

    public function testASemicolonInABacktickIdentifierDoesNotSplit(): void
    {
        // not valid in practice (MySQL identifiers can't really contain ';'), but the tokenizer should still treat
        // anything between backticks as opaque, the same way it does for a quoted string
        $sql = "SELECT 1 FROM `weird;name`;\nSELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('`weird;name`', $statements[0]);
    }

    public function testEscapedQuoteInsideAStringDoesNotEndIt(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('it\\'s a test; still inside');\nSELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('still inside', $statements[0]);
    }

    public function testDoubledQuoteEscapesItselfInsideAString(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('it''s a test; still inside');\nSELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('still inside', $statements[0]);
    }

    public function testADoubleDashNotFollowedByWhitespaceIsNotACommentStart(): void
    {
        // matches MySQL's own rule: '--' only starts a comment when followed by whitespace or end of input
        $sql = "SELECT 1--2;\nSELECT 3;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('1--2', $statements[0]);
    }

    public function testBlankAndWhitespaceOnlyChunksAreDropped(): void
    {
        $sql = "SELECT 1;\n\n   \n;SELECT 2;";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, array_filter($statements, fn($s) => trim($s) !== ''));
    }

    public function testATrailingStatementWithNoFinalSemicolonIsStillIncluded(): void
    {
        $sql = "SELECT 1;\nSELECT 2";
        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('SELECT 2', $statements[1]);
    }

    public function testRealCrmFieldMapDefinitionSplitsCorrectly(): void
    {
        // the exact statement that broke in production, reproduced verbatim
        $sql = <<<'SQL'
CREATE TABLE
    IF NOT EXISTS `crm_field_map` (
        `id`              INT NOT NULL AUTO_INCREMENT,
        PRIMARY KEY (`id`),
        -- crm_field included so several Cloudbeds fields can map to the same note target (note.psg/note.reservation/
        -- note.member collect lines from several fields; everything else holds one value)
        UNIQUE KEY `uq_gateway_object_field_crm` (`gateway_id`, `crm_object`, `hhk_field`, `crm_field`)
    ) ENGINE = InnoDB;
ALTER TABLE `crm_field_map` DROP INDEX IF EXISTS `uq_gateway_object_field`;
SQL;

        $statements = Patch::splitSqlStatements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString('UNIQUE KEY `uq_gateway_object_field_crm`', $statements[0]);
        $this->assertStringContainsString(') ENGINE = InnoDB', $statements[0]);
        $this->assertSame('ALTER TABLE `crm_field_map` DROP INDEX IF EXISTS `uq_gateway_object_field`', trim($statements[1]));
    }
}
