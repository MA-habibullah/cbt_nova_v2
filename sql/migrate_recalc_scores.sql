-- One-time migration: recalculate nilai_objektif, nilai_esai, skor_akhir
-- for all finished participants where nilai_objektif IS NULL.
--
-- Run via:
--   mysql -h 127.0.0.1 -P 3307 -u cbt_user -pcbt_pass cbt_nova < sql/migrate_recalc_scores.sql
-- or inside container:
--   docker exec -i cbt_nova_db mysql -u cbt_user -pcbt_pass cbt_nova < sql/migrate_recalc_scores.sql

UPDATE cbt_exam_participants p
JOIN (
    SELECT
        ep.id AS participant_id,
        -- Total bobot per kategori soal dari ujian
        SUM(CASE WHEN q.tipe IN ('pg','pg_kompleks','benar_salah','menjodohkan','isian')
                 THEN q.bobot_skor ELSE 0 END) AS bobot_obj,
        SUM(CASE WHEN q.tipe = 'essay'
                 THEN q.bobot_skor ELSE 0 END) AS bobot_essay,
        -- Total skor didapat siswa per kategori
        COALESCE(SUM(CASE WHEN q.tipe IN ('pg','pg_kompleks','benar_salah','menjodohkan','isian')
                          THEN sa.skor_didapat ELSE 0 END), 0) AS skor_obj,
        COALESCE(SUM(CASE WHEN q.tipe = 'essay'
                          THEN sa.skor_didapat ELSE 0 END), 0) AS skor_essay
    FROM cbt_exam_participants ep
    JOIN cbt_exam_questions   eq ON eq.exam_id     = ep.exam_id
    JOIN cbt_questions         q  ON eq.question_id = q.id
    LEFT JOIN cbt_student_answers sa
           ON sa.participant_id = ep.id AND sa.question_id = q.id
    WHERE ep.status = 'finished'
      AND ep.nilai_objektif IS NULL
    GROUP BY ep.id
) sub ON p.id = sub.participant_id
SET
    p.nilai_objektif = ROUND(
        CASE WHEN sub.bobot_obj   > 0 THEN sub.skor_obj   / sub.bobot_obj   * 100 ELSE 0 END, 2),

    p.nilai_esai     = ROUND(
        CASE WHEN sub.bobot_essay > 0 THEN sub.skor_essay / sub.bobot_essay * 100 ELSE 0 END, 2),

    p.skor_akhir     = ROUND(
        CASE
            WHEN sub.bobot_obj > 0 AND sub.bobot_essay > 0 THEN
                (sub.skor_obj   / sub.bobot_obj   * 100 * 0.5) +
                (sub.skor_essay / sub.bobot_essay * 100 * 0.5)
            WHEN sub.bobot_obj   > 0 THEN sub.skor_obj   / sub.bobot_obj   * 100
            WHEN sub.bobot_essay > 0 THEN sub.skor_essay / sub.bobot_essay * 100
            ELSE 0
        END, 2);
