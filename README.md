# Atividade 1 - Sistema de Gestão de Aeroporto

**Disciplina: Banco de Dados**
**Professor: Moisés Silva de Sousa**
**Aluno: Rafael Tominaga**

---

## 1 - Levantamento dos Dados

**Dados dos passageiros**
- Nome completo
- Data de nascimento
- CPF
- Email
- Telefone de contato

**Dados dos voos**
- Código do voo
- Origem
- Destino
- Horário de saída
- Horário de chegada
- Portão de embarque
- Status (Confirmado, Embarcando, Atrasado...)

**Dados das bagagens**
- Código de etiqueta
- Peso (kg)
- Status da bagagem (Despachada, Restituída, Extraviada...)
- Assento associado

**Dados das companhias aéreas**
- CNPJ
- Nome fantasia
- Código IATA
- País de origem

**Dados dos funcionários**
- Matrícula
- Nome completo
- Função
- Turno de trabalho
- Credencial de acesso

**Dados das aeronaves**
- Prefixo
- Modelo
- Capacidade de passageiros
- Capacidade de carga
- Capacidade de combustível
- Ano de fabricação

**Dados das passagens**
- Número do bilhete
- Data da compra
- Preço do bilhete
- Número do assento
- Classe
- ID do passageiro e do voo

**Dados de pagamentos**
- Código da transação
- Forma de pagamento
- Status do pagamento
- Data e hora da transação

---

## 2 - Inferências a partir dos dados

- Próximos voos do dia
- Voos atrasados
- Quantidade de passageiros por voo
- Histórico de viagens de um passageiro
- Quantidade de bagagens por voo
- Portão de embarque de cada voo
- Que aeronave utilizar para determinado trecho
- Circulação dentro do aeroporto por dia
- Quantidade de bagagem máxima
- Faturamento por companhia aérea
- Rotas populares
- Tempo médio de permanência
- Assentos vagos
- Taxa de extravio ou retenção de bagagens
- Inspeção aleatória de passageiros

---

## 3 - Dados indispensáveis

**CPF**
- A identificação do passageiro é primordial para a segurança do aereporto. Além disso, caso eu queira recomendar alguma promoção ou destino para ele baseado nas experiências anteriores, eu preciso do histórico de voos dele.

**Código de voo**
- O código de voo é de suma importância para a organização de chegadas e saídas das aeronaves.

**Origem e destino**
- É necessário saber o trecho de voo para poder alocar a melhor aeronave para o trecho. Além disso, o cliente precisa saber de onde ele está saindo e pra onde ele está indo.

**Prefixo da aeronave**
- É por meio deste código da aeronave que eu consigo rastreá-la.

**Limite de carga**
- É imprenscidível que a carga limite da aeronave não seja extrapolada, para que não haja acidentes.

**Código de bagagem**
- Para evitar desentendimentos com clientes.

**Nome completo do passageiro**
- Indispensável para validar o CPF informado.

**Matrícula dos funcionários**
- Para evitar que qualquer pessoa se passe como funcionário do aerporto e faça algo indevido, ou seja, segurança.

**Número do assento**
- Evitar venda do mesmo assento.

**Forma de pagamento**
- Garantir que a compra foi realizada com sucesso

---

## 4 - Perguntas para o cliente

- Como funciona o processo de check-in?
- Como funciona o processo de inspeção e raio-x?
- A cada quantas pessoas vocês fazem uma inspeção "aleatória"?
- Como é feito o processo de embarque?
- Como vocês verificam se a carga dentro da aeronave ainda está dentro do limite?
- Como vocês recomendam promoções ou destinos para os clientes?
- O que acontece caso vocês vendam o mesmo assento para pessoas diferentes (overbooking)?
- Como funciona o sistema de prioridades da pista?
- Como vocês lidam com a quatidade de pessoas dentro do aeroporto?
- De quanto em quanto tempo é feito a manutenção preventiva de aeronaves?

---

## 5 - Reflexão

**Qual foi a maior dificuldade encontrada durante a atividade?**
- Acredito que levantar dados de uma área que não conheço. Aparecem coisas que eu nem imaginava

**Você acredita que seja possível desenvolver um sistema sem realizar esse levantamento inicial? Justifique sua resposta.**
- Não, é preciso ter dados suficientes para entender o contexto do cliente e, dessa forma, desenvolver uma solução eficaz que atenda as demandas exigidas. 
