import { router } from '@inertiajs/react';
import AppShell from '@/components/Shell';
import { Money, PageHeader } from '@/components/ui';

export default function Index({ questions, periods, units, filters, answer }: { questions: { key: string; label: string }[]; periods: { id: number; name: string }[]; units: { id: number; code: string; name: string }[]; filters: Record<string, string | undefined>; answer: { understood: boolean; text: string; facts: { label: string; value: number | string }[] } | null }) {
    const ask = (form: HTMLFormElement) => {
        const data = Object.fromEntries(new FormData(form).entries());
        router.get('/inquiry', data, { preserveState: true });
    };

    return (
        <AppShell title="Tanya buku">
            <PageHeader kicker="Buku besar" title="Pertanyaan keuangan" lede="Jawaban hanya disusun dari jurnal yang sudah diposting. Sistem tidak mengarang angka dan tidak mengubah data." />
            <form className="grid-form" onSubmit={(event) => { event.preventDefault(); ask(event.currentTarget); }}>
                <label className="field"><span>Pertanyaan</span><select name="question" defaultValue={filters.question}>{questions.map((question) => <option key={question.key} value={question.key}>{question.label}</option>)}</select></label>
                <label className="field"><span>Periode</span><select name="period_id" defaultValue={filters.period_id}>{periods.map((period) => <option key={period.id} value={period.id}>{period.name}</option>)}</select></label>
                <label className="field"><span>Unit</span><select name="unit_id" defaultValue={filters.unit_id}>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                <label className="field"><span>Banding kiri</span><select name="left_id" defaultValue={filters.left_id}>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                <label className="field"><span>Banding kanan</span><select name="right_id" defaultValue={filters.right_id}>{units.map((unit) => <option key={unit.id} value={unit.id}>{unit.code}</option>)}</select></label>
                <button className="btn btn-primary">Tanyakan</button>
            </form>
            {answer && (
                <section className="panel panel-b" style={{ marginTop: 16 }}>
                    <p>{answer.text}</p>
                    {answer.facts.map((fact) => <div key={fact.label} className="compare"><span>{fact.label}</span><strong>{typeof fact.value === 'number' ? <Money value={fact.value} /> : fact.value}</strong><span /></div>)}
                </section>
            )}
        </AppShell>
    );
}
