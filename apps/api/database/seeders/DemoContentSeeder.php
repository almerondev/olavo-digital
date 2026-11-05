<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use App\Modules\Cast\Models\Teacher;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryType;
use App\Modules\Home\Models\HomeLayout;
use App\Modules\Home\Services\HomeService;
use App\Modules\Materials\Models\Material;
use App\Modules\Materials\Services\MaterialService;
use App\Modules\Materials\Services\MaterialWorkflow;
use Database\Seeders\Demo\DemoAssets;
use Illuminate\Database\Seeder;

/** Biblioteca de demonstração com arquivos reais, publicada pelo fluxo normal de materiais. */
class DemoContentSeeder extends Seeder
{
    private const TEACHERS = [
        ['Ana Beatriz Moura', 'ana-beatriz-moura', [176, 92, 52], ['historia', 'geografia', 'atualidades', 'esa-historia-do-brasil', 'esa-geografia-do-brasil'], ['História do Brasil', 'História Geral', 'Geopolítica'], 'Professora de História e Geografia, apaixonada por conectar o passado às notícias de hoje.'],
        ['Rafael Teixeira', 'rafael-teixeira', [52, 110, 196], ['matematica', 'fisica', 'esa-matematica', 'iffar-matematica'], ['Funções', 'Geometria', 'Mecânica'], 'Professor de Matemática e Física que transforma fórmulas em raciocínio.'],
        ['Camila Duarte', 'camila-duarte', [34, 146, 122], ['quimica', 'biologia'], ['Química Orgânica', 'Genética', 'Ecologia'], 'Professora de Química e Biologia, especialista em explicar o invisível.'],
        ['Marcos Vilela', 'marcos-vilela', [190, 72, 112], ['linguagens', 'redacao', 'esa-portugues', 'esa-ingles', 'iffar-portugues'], ['Redação nota mil', 'Literatura', 'Interpretação'], 'Professor de Linguagens e Redação, responsável pela preparação para a prova discursiva.'],
    ];

    /** matéria => [cor, professor, materiais [título, descrição, tipo]] */
    private const LIBRARY = [
        'historia' => [[176, 92, 52], 0, [
            ['Revolução Industrial: das máquinas às fábricas', 'Como o carvão, o tear mecânico e o trabalho assalariado mudaram a Inglaterra do século XVIII.', 'pdf'],
            ['Era Vargas em 40 minutos', 'Do governo provisório ao Estado Novo: o que mais cai sobre o período.', 'pdf'],
            ['Linha do tempo do Brasil República', 'Mapa visual com os principais marcos políticos desde 1889.', 'image'],
            ['Guerra Fria: resumo para o vestibular', 'Blocos, conflitos indiretos e o fim da União Soviética.', 'pdf'],
        ]],
        'matematica' => [[52, 110, 196], 1, [
            ['Funções do 2º grau sem mistério', 'Raízes, vértice e gráfico, com exercícios comentados.', 'pdf'],
            ['Geometria plana: fórmulas essenciais', 'Áreas, perímetros e relações no triângulo em uma revisão direta.', 'pdf'],
            ['Porcentagem e juros no ENEM', 'Os tipos de questão que mais aparecem na prova.', 'pdf'],
        ]],
        'fisica' => [[92, 76, 188], 1, [
            ['Leis de Newton na prática', 'Força, massa e aceleração em situações do cotidiano.', 'pdf'],
            ['Eletrodinâmica: circuitos simples', 'Corrente, resistência e potência explicadas passo a passo.', 'pdf'],
        ]],
        'quimica' => [[34, 146, 122], 2, [
            ['Tabela periódica: como ler de verdade', 'Famílias, períodos e propriedades que ajudam a resolver questões.', 'pdf'],
            ['Estequiometria em cinco passos', 'Um método simples para acertar os cálculos.', 'pdf'],
        ]],
        'biologia' => [[76, 150, 62], 2, [
            ['Citologia: a célula por dentro', 'Organelas e suas funções, com esquemas para revisar.', 'pdf'],
            ['Mapa da genética mendeliana', 'As leis de Mendel organizadas em um esquema visual.', 'image'],
            ['Ecologia e ciclos biogeoquímicos', 'Cadeias alimentares, carbono e nitrogênio.', 'pdf'],
        ]],
        'geografia' => [[28, 134, 164], 0, [
            ['Clima e vegetação do Brasil', 'Biomas, massas de ar e o que os vestibulares cobram.', 'pdf'],
            ['Globalização e blocos econômicos', 'Fluxos, organizações e desigualdades.', 'pdf'],
        ]],
        'linguagens' => [[190, 72, 112], 3, [
            ['Interpretação de texto: método de leitura', 'Como localizar a ideia central e evitar armadilhas.', 'pdf'],
            ['Modernismo brasileiro', 'Fases, autores e obras mais cobradas.', 'pdf'],
        ]],
        'redacao' => [[202, 134, 34], 3, [
            ['Estrutura da redação nota mil', 'Introdução, argumentos e proposta de intervenção.', 'pdf'],
            ['Repertório sociocultural: como usar', 'Exemplos que somam pontos sem parecer decorados.', 'pdf'],
            ['Cartilha oficial do ENEM', 'Página do Inep com provas, gabaritos e informações do exame.', 'link'],
        ]],
        'atualidades' => [[120, 120, 140], 0, [
            ['Mudanças climáticas e acordos globais', 'O que acompanhar para as provas deste ano.', 'pdf'],
        ]],
    ];

    /** matéria => [cor, professor, vídeos [título, descrição]] */
    private const VIDEOS = [
        'historia' => [[176, 92, 52], 0, [
            ['Revolução Francesa: do Antigo Regime ao Terror', 'Da crise da monarquia à queda de Robespierre, em uma aula direta para o vestibular.'],
            ['Brasil Colônia: açúcar, ouro e escravidão', 'Como a economia colonial moldou a sociedade brasileira.'],
            ['Primeira Guerra Mundial em uma aula', 'Causas, alianças e consequências do conflito.'],
        ]],
        'matematica' => [[52, 110, 196], 1, [
            ['Equações do 1º grau: aula completa', 'Do básico aos problemas de enunciado.'],
            ['Probabilidade sem decorar fórmulas', 'Raciocínio passo a passo para as questões do ENEM.'],
            ['Trigonometria no triângulo retângulo', 'Seno, cosseno e tangente com exemplos práticos.'],
        ]],
        'fisica' => [[92, 76, 188], 1, [
            ['Cinemática: movimento uniforme', 'Velocidade, gráficos e encontro de móveis.'],
            ['Óptica: espelhos e lentes', 'Formação de imagens explicada com desenhos simples.'],
        ]],
        'quimica' => [[34, 146, 122], 2, [
            ['Ligações químicas na prática', 'Iônica, covalente e metálica sem complicação.'],
            ['Funções orgânicas: como reconhecer', 'Os grupos funcionais que mais caem.'],
        ]],
        'biologia' => [[76, 150, 62], 2, [
            ['DNA, RNA e síntese de proteínas', 'Do gene à proteína, com analogias que ajudam a lembrar.'],
            ['Sistema imunológico', 'Como o corpo se defende e por que as vacinas funcionam.'],
        ]],
        'geografia' => [[28, 134, 164], 0, [
            ['Urbanização brasileira', 'Metrópoles, migrações e problemas das cidades.'],
            ['Geopolítica do Oriente Médio', 'Fronteiras, petróleo e conflitos atuais.'],
        ]],
        'linguagens' => [[190, 72, 112], 3, [
            ['Figuras de linguagem que caem no ENEM', 'Metáfora, ironia e outras, em textos reais.'],
            ['Variação linguística', 'Norma-padrão, registros e preconceito linguístico.'],
        ]],
        'redacao' => [[202, 134, 34], 3, [
            ['Como escrever a introdução', 'Três modelos para começar bem o texto.'],
            ['Proposta de intervenção completa', 'Os cinco elementos cobrados na competência 5.'],
        ]],
        'atualidades' => [[120, 120, 140], 0, [
            ['Inteligência artificial e sociedade', 'O debate que pode aparecer nas provas.'],
        ]],
        'esa-matematica' => [[52, 110, 196], 1, [
            ['ESA: conjuntos e funções', 'Os temas de Matemática mais cobrados na prova da ESA.'],
            ['ESA: geometria espacial', 'Volumes e áreas com questões de provas anteriores.'],
        ]],
        'esa-portugues' => [[190, 72, 112], 3, [
            ['ESA: interpretação e gramática', 'Revisão de Português para a prova da ESA.'],
        ]],
        'esa-historia-do-brasil' => [[176, 92, 52], 0, [
            ['ESA: Brasil Império', 'Do Primeiro Reinado à Proclamação da República.'],
        ]],
        'iffar-matematica' => [[52, 110, 196], 1, [
            ['IFFar: operações e frações', 'Base de Matemática para o processo seletivo do IFFar.'],
        ]],
        'iffar-portugues' => [[190, 72, 112], 3, [
            ['IFFar: leitura e interpretação', 'Como responder às questões de Português do IFFar.'],
        ]],
    ];

    private const PRODUCTIONS = [
        ['Bastidores do Olavo: um dia de estúdio', 'Como nascem as aulas gravadas, da pauta à edição.'],
        ['Histórias de aprovação', 'Ex-alunos contam como foi a preparação para o vestibular.'],
        ['Olavo Pré-Vestibular: nossa história', 'Uma produção sobre a trajetória do cursinho.'],
    ];

    private const HERO_VIDEO = 'Revolução Francesa: do Antigo Regime ao Terror';

    private const FEATURED = [self::HERO_VIDEO, 'Probabilidade sem decorar fórmulas', 'DNA, RNA e síntese de proteínas', 'Como escrever a introdução', 'Bastidores do Olavo: um dia de estúdio', 'Ligações químicas na prática', 'Revolução Industrial: das máquinas às fábricas', 'Funções do 2º grau sem mistério', 'Estrutura da redação nota mil', 'Citologia: a célula por dentro', 'Leis de Newton na prática', 'Tabela periódica: como ler de verdade'];

    private const ENEM_URL = 'https://www.gov.br/inep/pt-br/areas-de-atuacao/avaliacao-e-exames-educacionais/enem';

    public function run(): void
    {
        if (Material::exists()) {
            return;
        }

        $assets = app(DemoAssets::class);
        $materials = app(MaterialService::class);
        $workflow = app(MaterialWorkflow::class);
        $admin = User::where('email', 'admin@olavo.test')->firstOrFail();

        $teachers = collect(self::TEACHERS)->map(function (array $data, int $index) use ($assets) {
            [$name, $slug, $color, $subjects, $specialties, $bio] = $data;

            $teacher = Teacher::firstOrCreate(['slug' => $slug], ['display_name' => $name]);
            $teacher->update([
                'short_bio' => $bio,
                'bio' => "{$bio} No Olavo Pré-Vestibular, prepara os alunos com aulas diretas, muita resolução de questões e foco no que mais cai nas provas.",
                'specialties' => $specialties,
                'sort_order' => $index,
                'photo_upload_id' => $assets->portrait($name, $color, 900 + $index)->id,
                'banner_upload_id' => $assets->banner("{$name} banner", $color, 950 + $index)->id,
            ]);
            $teacher->categories()->sync(Category::whereIn('slug', $subjects)->pluck('id'));
            $teacher->socialLinks()->create(['network' => 'instagram', 'url' => "https://www.instagram.com/{$slug}"]);

            return $teacher;
        });

        $seed = 1;

        foreach (self::LIBRARY as $slug => [$color, $teacherIndex, $items]) {
            $category = Category::where('slug', $slug)->firstOrFail();

            foreach ($items as [$title, $description, $type]) {
                $seed++;
                $file = match ($type) {
                    'pdf' => $assets->pdf($title, $this->paragraphs($title, $description)),
                    'image' => $assets->privateImage($title, $color, $seed),
                    default => null,
                };

                $material = $materials->save(null, [
                    'title' => $title,
                    'description' => $description,
                    'type' => $type,
                    'visibility' => 'all',
                    'teacher_id' => $teachers[$teacherIndex]->id,
                    'cover_upload_id' => $assets->cover($title, $color, $seed)->id,
                    'banner_upload_id' => $assets->banner($title, $color, $seed)->id,
                    'file_upload_id' => $file?->id,
                    'external_url' => $type === 'link' ? self::ENEM_URL : null,
                    'allow_download' => $type === 'pdf',
                    'is_featured' => in_array($title, self::FEATURED, true),
                    'category_ids' => [$category->id],
                    'tags' => [$category->name, 'Vestibular'],
                ], $admin);

                $workflow->publish($material, $admin);
                // Datas espalhadas, para Novidades e o selo de novo refletirem a ordem real.
                $material->update(['published_at' => now()->subDays(($seed % 6) * 3)->subHours($seed)]);
            }
        }

        // Clipes de demonstração só existem para as matérias do ENEM.
        $clips = ['esa-matematica' => 'matematica', 'esa-portugues' => 'linguagens', 'esa-historia-do-brasil' => 'historia', 'iffar-matematica' => 'matematica', 'iffar-portugues' => 'linguagens'];

        foreach (self::VIDEOS as $slug => [$color, $teacherIndex, $items]) {
            $category = Category::where('slug', $slug)->firstOrFail();

            foreach ($items as [$title, $description]) {
                $this->publishVideo($title, $description, $clips[$slug] ?? $slug, $color, ++$seed, $teachers[$teacherIndex]->id, $category->id, $category->name, $admin);
            }
        }

        $productions = Category::firstOrCreate(['slug' => 'producoes-olavo'], [
            'category_type_id' => CategoryType::where('slug', 'especial')->value('id'),
            'name' => 'Produções Olavo',
            'sort_order' => 100,
        ]);

        foreach (self::PRODUCTIONS as [$title, $description]) {
            $this->publishVideo($title, $description, 'atualidades', [150, 60, 60], ++$seed, $teachers[0]->id, $productions->id, $productions->name, $admin);
        }

        HomeLayout::where('surface', HomeLayout::SURFACE_HOME)->first()?->sections()->create([
            'title' => 'Produções Olavo',
            'source_type' => 'category',
            'category_id' => $productions->id,
            'sort_order' => 1,
        ]);

        // O hero usa o destaque mais recente.
        Material::where('title', self::HERO_VIDEO)->update(['published_at' => now()]);

        app(HomeService::class)->flush();
    }

    /** @param array{0: int, 1: int, 2: int} $color */
    private function publishVideo(string $title, string $description, string $clip, array $color, int $seed, int $teacherId, int $categoryId, string $categoryName, User $admin): void
    {
        $assets = app(DemoAssets::class);

        $material = app(MaterialService::class)->save(null, [
            'title' => $title,
            'description' => $description,
            'type' => 'video',
            'visibility' => 'all',
            'teacher_id' => $teacherId,
            'cover_upload_id' => $assets->cover($title, $color, $seed)->id,
            'banner_upload_id' => $assets->banner($title, $color, $seed)->id,
            'file_upload_id' => $assets->video($clip, $title)->id,
            'is_featured' => in_array($title, self::FEATURED, true),
            'category_ids' => [$categoryId],
            'tags' => [$categoryName, 'Videoaula'],
        ], $admin);

        app(MaterialWorkflow::class)->publish($material, $admin);
        $material->update(['published_at' => now()->subDays(($seed % 7) * 2)->subHours($seed)]);
    }

    /** @return list<string> */
    private function paragraphs(string $title, string $description): array
    {
        return [
            $description,
            "Este arquivo faz parte dos dados de demonstração do Olavo Digital e foi gerado automaticamente. O conteúdo definitivo de \"{$title}\" será publicado pela equipe de professores.",
            'Use este material para conferir o leitor integrado: a navegação entre páginas, o zoom e o carregamento sob demanda.',
            'Na plataforma final, cada professor publica os próprios arquivos pelo painel, e os materiais passam pelas mesmas regras de acesso aplicadas aqui.',
        ];
    }
}
