# Discussão Arquitetural: Regra de Negócio no Frontend vs. Backend

## 📌 Questão

> **Onde a regra de negócio deve ficar: no frontend (JavaScript) ou no backend (PHP)? Por quê?**

---

## 🎯 Resposta Direta

A regra de negócio deve residir **primordialmente no backend (PHP)**.

Embora o frontend possa espelhar validações simples para melhorar a experiência do usuário (UX), a fonte da verdade, a computação de estados derivados e as decisões institucionais de domínio devem ser centralizadas no servidor.

---

## 🔍 Justificativa Detalhada

### 1. Segurança e Integridade dos Dados
* O código executado no navegador (*client-side*) está totalmente exposto e pode ser visualizado, depurado ou alterado através do DevTools/Console.
* Cálculos ou regras sensíveis deixados exclusivamente no JavaScript podem ser facilmente manipulados por usuários maliciosos ou scripts de terceiros, gerando estados inválidos ou inconsistentes.
* O backend é um ambiente controlado e isolado, garantindo que o processamento ocorra exatamente como especificado pela regra institucional.

### 2. Consistência e Reutilização (Única Fonte da Verdade)
* Em sistemas escaláveis, a mesma API frequentemente fornece dados para múltiplos clientes:
  * Aplicação Web (HTML / JS / React);
  * Aplicativos Móveis (Android / iOS);
  * Relatórios, integrações externas e rotinas agendadas (CRON).
* Se a regra de aprovação estiver no JavaScript da página Web, cada novo cliente precisará reimplementar a mesma lógica, abrindo margem para divergências entre plataformas.
* Centralizar a regra no backend permite alterar o critério (por exemplo, nota de corte de $7.0$ para $6.0$) em um único lugar, refletindo instantaneamente em todos os clientes consumidores.

### 3. Manutenção e Separação de Responsabilidades (SoC)
* **Princípio da Separação de Preocupações:**
  * **Frontend:** Responsável pela camada de visualização, formatação, layout e interação com o usuário (UI/UX).
  * **Backend:** Responsável pela segurança, validação de domínio, persistência e orquestração dos dados.
* Desacoplar a regra de negócio da interface gráfica simplifica a realização de testes unitários automatizados e previne que mudanças visuais acidentais afetem a lógica dos dados.

### 4. Papel do Frontend
* O papel do frontend não é tomar a decisão final de negócio, e sim:
  * Renderizar com clareza o estado retornado pela API;
  * Oferecer *feedbacks* visuais imediatos ao usuário (validação de formulários, alertas preventivos);
  * Aplicar máscaras e formatações regionais (ex: converter `7.0` para `7,0`).

---

## ⚖️ Comparativo de Abordagens

| Critério | Abordagem 1: Regra no Frontend (JS) | Abordagem 2: Regra no Backend (PHP) |
| :--- | :--- | :--- |
| **Segurança** | ⚠️ Baixa (código vulnerável à alteração local) | ✅ Alta (ambiente controlado pelo servidor) |
| **Reutilização** | ❌ Nula (restrita àquela página Web) | ✅ Alta (disponível para qualquer cliente/API) |
| **Manutenibilidade** | ❌ Complexa (exige mudanças em cada interface) | ✅ Simples (alteração centralizada na API) |
| **Integridade** | ⚠️ Risco de inconsistência entre clientes | ✅ Dados 100% consistentes e auditáveis |
| **Papel do Cliente** | Sobrecarregado com regras de domínio | Focado estritamente na apresentação (UI) |

---

## 🔄 Fluxo de Processamento Recomendado

```text
[ Banco de Dados MySQL ]
          │
          ▼
    [ Backend PHP ]
   (Aplica a Regra: se nota >= 7.0 -> "Aprovado", senão -> "Reprovado")
          │
          ▼
   [ Resposta JSON ]
  { "nome": "Ana", "nota": 8.5, "status": "Aprovado" }
          │
          ▼
[ Frontend JavaScript / Fetch API ]
   (Lê o status pronto e renderiza a interface)
          │
          ▼
    [ Tabela HTML ]
```
