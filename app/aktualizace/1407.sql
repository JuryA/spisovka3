DELETE FROM [dokument_to_spis] WHERE [dokument_id] NOT IN (SELECT [id] FROM [dokument]);