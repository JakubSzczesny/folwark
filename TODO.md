# TODO

- [ ] **Kopie zapasowe poza serwerem** – po codziennej kopii (`deploy/backup.sh`) automatycznie kopiować plik `folwark-RRRR-MM-DD.tar` poza serwer (NAS w sieci lokalnej albo chmura, np. przez `rclone`), z kontrolą, że kopia doszła, i powiadomieniem właściciela, gdy się nie uda. Kopia zawiera `ENCRYPTION_KEY`, więc w chmurze tylko zaszyfrowana.
