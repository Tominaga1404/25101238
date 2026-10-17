// js/app.js - ABORDAGEM 1 (Status no Frontend)
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('tabela-alunos');

    fetch('api/alunos.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Falha na requisição: ' + response.statusText);
            }
            return response.json();
        })
        .then(alunos => {
            tbody.innerHTML = '';

            alunos.forEach(aluno => {
                // Cálculo no cliente
                const status = aluno.nota >= 7.0 ? 'Aprovado' : 'Reprovado';
                const statusClass = status === 'Aprovado' ? 'status-aprovado' : 'status-reprovado';
                const notaFormatada = Number(aluno.nota).toFixed(1).replace('.', ',');

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${aluno.nome}</td>
                    <td>${notaFormatada}</td>
                    <td><span class="${statusClass}">${status}</span></td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Erro:', error);
            tbody.innerHTML = '<tr><td colspan="3">Erro ao carregar dados.</td></tr>';
        });
});

