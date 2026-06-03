-- SOURCE: CIE_Master_Developer_Build_Spec.docx Section 6.5

UPDATE content_briefs SET status = 'open'        WHERE status = 'OPEN';
UPDATE content_briefs SET status = 'in_progress' WHERE status = 'IN_PROGRESS';
UPDATE content_briefs SET status = 'completed'   WHERE status = 'COMPLETED';
UPDATE content_briefs SET status = 'completed'   WHERE status = 'CANCELLED';

ALTER TABLE content_briefs
    MODIFY COLUMN status ENUM('open','in_progress','completed','overdue')
    NOT NULL DEFAULT 'open';
