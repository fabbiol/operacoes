-- Dados de exemplo de todo ambiente de teste (fictícios). Entram depois que o app criou as tabelas.
-- O fuso é o mesmo do app (Cuiaba, -04:00): o NOW() daqui tem de bater com o NOW() que o app grava.
SET time_zone = '-04:00';
INSERT INTO materiais (material) VALUES ('Brita 1'), ('Pó de pedra');
INSERT INTO caminhoes (placa, marca, modelo, ano, capacidade_t) VALUES ('ABC1D23','Volvo','FMX 500',2019,30.00), ('QBK2F41','Scania','G440',2020,28.00), ('XYZ9K88','Mercedes','Axor',2018,25.00);
INSERT INTO retroescavadeiras (maquina, marca, modelo, ano) VALUES ('Retro 1','JCB','3CX',2021), ('Retro 2','CAT','416',2019);
INSERT INTO pas_carregadeiras (maquina, marca, modelo, ano) VALUES ('Pá 1','CAT','924K',2020), ('Pá 2','Volvo','L60',2018);
INSERT INTO fila_carregamentos (ordem, data, cliente, pedido, nf, material_id, quantidade_autorizada, unidade, status, autorizado_por, autorizado_em)
VALUES ('CAR-001', NOW(), 'Construtora Alfa', 'P-10', 'NF-100', 1, 30.00, 't', 'liberado', 'Fabio', NOW()),
       ('CAR-002', NOW(), 'Obra Beta', 'P-11', 'NF-101', 2, 12.50, 't', 'liberado', 'Fabio', NOW()),
       ('CAR-003', NOW(), 'Cliente Gama', 'P-12', 'NF-102', 1, 8.00, 'm3', 'liberado', 'Fabio', NOW());
