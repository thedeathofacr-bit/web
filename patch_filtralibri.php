<?php
/**
 * PATCH per filtra_libri.php
 * 
 * Nella query principale che recupera i libri, aggiungi il JOIN con recensioni
 * per restituire media_voti e num_recensioni in ogni riga.
 * 
 * Sostituisci la SELECT dei libri con questa:
 */

// PRIMA (esempio tipico):
//   SELECT * FROM libri WHERE id_libreria = ? ...

// DOPO — aggiungi questo JOIN e questi campi:
$esempio_query = "
    SELECT 
        l.*,
        COALESCE(AVG(r.voto), 0)  AS media_voti,
        COUNT(r.id)                AS num_recensioni
    FROM libri l
    LEFT JOIN recensioni r ON r.id_libro = l.id AND r.id_libreria = l.id_libreria
    WHERE l.id_libreria = ?
    -- ... resto dei tuoi filtri (AND, ORDER BY, LIMIT, ecc.) invariato
    GROUP BY l.id
";

/**
 * In questo modo ogni libro nell'array JSON avrà:
 *   "media_voti": 4.2,
 *   "num_recensioni": 7
 * 
 * e l'index.php li mostrerà automaticamente come stelline nelle card e nella tabella.
 */
